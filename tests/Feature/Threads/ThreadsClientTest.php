<?php

namespace Tests\Feature\Threads;

use App\Services\Threads\ThreadsApiException;
use App\Services\Threads\ThreadsClient;
use App\Services\Threads\ThreadsErrorKind;
use Closure;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ThreadsClientTest extends TestCase
{
    use InteractsWithThreads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-48, NFR-5: one request per call with the documented parameters, no paging.
     */
    public function test_keyword_search_sends_the_documented_parameters_without_paging(): void
    {
        $this->fakeThreads([
            $this->graphUrl('v1.0/keyword_search').'*' => Http::response([
                'data' => [$this->threadsItem('1001')],
                'paging' => ['cursors' => ['after' => 'NEXT-PAGE']],
            ]),
        ]);

        $result = app(ThreadsClient::class)->keywordSearch('token-abc', 'laravel developer', 1_700_000_000, 1_700_003_600);

        $this->assertSame([$this->threadsItem('1001')], $result);
        $this->assertCount(1, Http::recorded());

        [$request] = Http::recorded()[0];
        $parameters = $this->requestParameters($request);

        $this->assertSame('GET', $request->method());
        $this->assertStringStartsWith($this->graphUrl('v1.0/keyword_search'), $request->url());
        $this->assertEqualsCanonicalizing(
            ['q', 'search_type', 'limit', 'since', 'until', 'fields', 'access_token'],
            array_keys($parameters),
        );
        $this->assertSame('laravel developer', $parameters['q']);
        $this->assertSame('RECENT', $parameters['search_type']);
        $this->assertSame('100', (string) $parameters['limit']);
        $this->assertSame('1700000000', (string) $parameters['since']);
        $this->assertSame('1700003600', (string) $parameters['until']);
        $this->assertSame('id,text,permalink,timestamp,username', $parameters['fields']);
        $this->assertSame('token-abc', $parameters['access_token']);
    }

    /**
     * SPEC-01 AC-5 (TA-2), NFR-5: the authorization code is exchanged with a form POST.
     */
    public function test_code_exchange_posts_the_documented_form_parameters(): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response([
                'access_token' => 'short-token',
                'user_id' => '29580006461583503',
            ]),
        ]);

        $result = app(ThreadsClient::class)->exchangeCode('auth-code');

        $this->assertSame(['access_token' => 'short-token', 'user_id' => '29580006461583503'], $result);

        [$request] = Http::recorded()[0];
        $parameters = $this->requestParameters($request);

        $this->assertSame('POST', $request->method());
        $this->assertSame('test-app-id', $parameters['client_id']);
        $this->assertSame('test-app-secret', $parameters['client_secret']);
        $this->assertSame('auth-code', $parameters['code']);
        $this->assertSame('authorization_code', $parameters['grant_type']);
        $this->assertSame('http://localhost/auth/threads/callback', $parameters['redirect_uri']);
    }

    /**
     * SPEC-01 AC-5 (TA-3): the short-lived token is exchanged for a long-lived one.
     */
    public function test_long_lived_exchange_sends_the_documented_parameters(): void
    {
        $this->fakeThreads([
            $this->graphUrl('access_token').'*' => Http::response([
                'access_token' => 'long-token',
                'token_type' => 'bearer',
                'expires_in' => 5_184_000,
            ]),
        ]);

        $result = app(ThreadsClient::class)->exchangeForLongLivedToken('short-token');

        $this->assertSame(['access_token' => 'long-token', 'expires_in' => 5_184_000], $result);

        [$request] = Http::recorded()[0];
        $parameters = $this->requestParameters($request);

        $this->assertSame('GET', $request->method());
        $this->assertSame('th_exchange_token', $parameters['grant_type']);
        $this->assertSame('test-app-secret', $parameters['client_secret']);
        $this->assertSame('short-token', $parameters['access_token']);
    }

    /**
     * SPEC-01 AC-5 (TA-4): the profile is read with `fields=id,username,name`.
     */
    public function test_profile_call_sends_the_documented_parameters(): void
    {
        $this->fakeThreads([
            $this->graphUrl('v1.0/me').'*' => Http::response([
                'id' => '29580006461583503',
                'username' => 'elrondhalfelven',
                'name' => 'Elrond',
            ]),
        ]);

        $result = app(ThreadsClient::class)->me('long-token');

        $this->assertSame(['id' => '29580006461583503', 'username' => 'elrondhalfelven', 'name' => 'Elrond'], $result);

        [$request] = Http::recorded()[0];
        $parameters = $this->requestParameters($request);

        $this->assertSame('id,username,name', $parameters['fields']);
        $this->assertSame('long-token', $parameters['access_token']);
    }

    /**
     * SPEC-01 AC-28 (TA-5): a long-lived token is refreshed with `th_refresh_token`.
     */
    public function test_refresh_sends_the_documented_parameters(): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => Http::response([
                'access_token' => 'new-token',
                'token_type' => 'bearer',
                'expires_in' => 5_184_000,
            ]),
        ]);

        $result = app(ThreadsClient::class)->refreshToken('old-token');

        $this->assertSame(['access_token' => 'new-token', 'expires_in' => 5_184_000], $result);

        [$request] = Http::recorded()[0];
        $parameters = $this->requestParameters($request);

        $this->assertSame('th_refresh_token', $parameters['grant_type']);
        $this->assertSame('old-token', $parameters['access_token']);
    }

    /**
     * SPEC-01 AC-16, AC-30, AC-55: OAuth error 190 means the token is invalid.
     */
    public function test_error_190_is_classified_as_token_invalid(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsError(190, 'Error validating access token: Session has expired')]);

        $exception = $this->catchApiException(
            fn () => app(ThreadsClient::class)->keywordSearch('token', 'laravel', 1_700_000_000, 1_700_003_600),
        );

        $this->assertSame(ThreadsErrorKind::TokenInvalid, $exception->kind);
        $this->assertSame(190, $exception->threadsCode);
        $this->assertSame(400, $exception->httpStatus);
    }

    /**
     * SPEC-01 AC-58, D-8: code 190 is checked before the "limit" message rule.
     */
    public function test_error_190_wins_over_a_message_that_mentions_limit(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsError(190, 'Session limit exceeded')]);

        $exception = $this->catchApiException(
            fn () => app(ThreadsClient::class)->keywordSearch('token', 'laravel', 1_700_000_000, 1_700_003_600),
        );

        $this->assertSame(ThreadsErrorKind::TokenInvalid, $exception->kind);
    }

    /**
     * @return array<string, array{int, array<string, mixed>}>
     */
    public static function rateLimitResponses(): array
    {
        $error = fn (int $code, string $message): array => ['error' => ['message' => $message, 'type' => 'OAuthException', 'code' => $code]];

        return [
            'error code 4' => [400, $error(4, 'Application failed')],
            'error code 17' => [400, $error(17, 'User failed')],
            'error code 32' => [400, $error(32, 'Page failed')],
            'error code 341' => [400, $error(341, 'Application failed')],
            'error code 613' => [400, $error(613, 'Calls failed')],
            'http 429' => [429, $error(0, 'Too many requests')],
            'message with limit' => [400, $error(1, 'Application request limit reached')],
            'message with upper case LIMIT' => [400, $error(1, 'RATE LIMIT hit')],
        ];
    }

    /**
     * SPEC-01 AC-58 (D-8): the listed codes, HTTP 429 and "limit" messages are rate limits.
     *
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('rateLimitResponses')]
    public function test_rate_limit_codes_http_429_and_limit_messages_are_classified_as_rate_limited(int $status, array $body): void
    {
        $this->fakeKeywordSearch(['laravel' => Http::response($body, $status)]);

        $exception = $this->catchApiException(
            fn () => app(ThreadsClient::class)->keywordSearch('token', 'laravel', 1_700_000_000, 1_700_003_600),
        );

        $this->assertSame(ThreadsErrorKind::RateLimited, $exception->kind);
        $this->assertSame($status, $exception->httpStatus);
    }

    /**
     * SPEC-01 AC-56, AC-31: HTTP 5xx and connection failures are transient.
     */
    #[DataProvider('transientFailures')]
    public function test_server_errors_and_connection_failures_are_classified_as_transient(mixed $failure): void
    {
        $this->fakeThreads([
            $this->graphUrl('v1.0/keyword_search').'*' => $failure instanceof Closure ? $failure : Http::response(['error' => ['message' => 'Server error', 'code' => 2]], $failure),
        ]);

        $exception = $this->catchApiException(
            fn () => app(ThreadsClient::class)->keywordSearch('token', 'laravel', 1_700_000_000, 1_700_003_600),
        );

        $this->assertSame(ThreadsErrorKind::Transient, $exception->kind);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function transientFailures(): array
    {
        return [
            'http 500' => [500],
            'http 503' => [503],
            'connection failure' => [HttpFactory::failedConnection('cURL error 28: Operation timed out')],
        ];
    }

    /**
     * SPEC-01 AC-57, AC-32: any other error keeps the Threads code.
     */
    public function test_other_errors_are_classified_as_other_with_the_threads_code(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsError(100, 'Invalid parameter')]);

        $exception = $this->catchApiException(
            fn () => app(ThreadsClient::class)->keywordSearch('token', 'laravel', 1_700_000_000, 1_700_003_600),
        );

        $this->assertSame(ThreadsErrorKind::Other, $exception->kind);
        $this->assertSame(100, $exception->threadsCode);
        $this->assertSame(400, $exception->httpStatus);
    }

    /**
     * SPEC-01 AC-6 (TA-2): the `{error_type, code, error_message}` error format is recognised.
     */
    public function test_code_exchange_error_format_is_recognised(): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response([
                'error_type' => 'OAuthException',
                'code' => 400,
                'error_message' => 'Matching code was not found or was already used',
            ], 400),
        ]);

        $exception = $this->catchApiException(fn () => app(ThreadsClient::class)->exchangeCode('used-code'));

        $this->assertSame(ThreadsErrorKind::Other, $exception->kind);
        $this->assertSame(400, $exception->threadsCode);
        $this->assertSame(400, $exception->httpStatus);
    }

    /**
     * SPEC-01 NFR-2, UI-11: neither the exception nor its sanitized body leaks the token.
     */
    #[DataProvider('tokenLeakingFailures')]
    public function test_exception_message_and_body_never_contain_the_access_token(string $scenario): void
    {
        $token = 'SECRET-TOKEN-123';

        $this->fakeThreads([
            $this->graphUrl('v1.0/me').'*' => $scenario === 'body'
                ? $this->threadsError(100, "Invalid token access_token={$token} supplied; token {$token}")
                : Http::failedConnection("cURL error 28 for https://graph.threads.net/v1.0/me?fields=id&access_token={$token}"),
        ]);

        $exception = $this->catchApiException(fn () => app(ThreadsClient::class)->me($token));

        $this->assertStringNotContainsString($token, $exception->getMessage());
        $this->assertStringNotContainsString($token, (string) $exception->sanitizedBody);
        $this->assertNull($exception->getPrevious());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tokenLeakingFailures(): array
    {
        return [
            'error body repeats the token' => ['body'],
            'connection error carries the request url' => ['connection'],
        ];
    }

    private function catchApiException(Closure $call): ThreadsApiException
    {
        try {
            $call();
        } catch (ThreadsApiException $exception) {
            return $exception;
        }

        $this->fail('Expected a ThreadsApiException.');
    }
}
