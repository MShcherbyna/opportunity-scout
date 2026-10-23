<?php

namespace Tests\Concerns;

use App\Models\ThreadsConnection;
use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use BackedEnum;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for the Threads feature tests: configuration, fake Graph API
 * responses, signed Meta requests, log capture and common records.
 */
trait InteractsWithThreads
{
    protected string $threadsAppSecret = 'test-app-secret';

    protected string $threadsAppId = 'test-app-id';

    /**
     * Boot hook (called by the framework's trait setup): adds `requiredProp()` to test responses.
     * It returns an Inertia prop and fails with a clear message when the prop is missing or null.
     */
    protected function setUpInteractsWithThreads(): void
    {
        TestResponse::macro('requiredProp', function (string $key): mixed {
            /** @var TestResponse<Response> $this */
            $value = $this->inertiaProps($key);
            Assert::assertNotNull($value, "Inertia prop [{$key}] is missing or null.");

            return $value;
        });
    }

    /**
     * Point the Threads settings at fixed test values.
     */
    protected function configureThreads(): void
    {
        config([
            'services.threads.client_id' => $this->threadsAppId,
            'services.threads.client_secret' => $this->threadsAppSecret,
            'services.threads.redirect_uri' => 'http://localhost/auth/threads/callback',
            'services.threads.authorize_url' => 'https://threads.net/oauth/authorize',
            'services.threads.graph_url' => 'https://graph.threads.net',
            'services.threads.own_posts_only' => false,
        ]);
    }

    /**
     * Fake the given Graph API responses and reject every other request.
     *
     * @param  array<string, mixed>  $responses
     */
    protected function fakeThreads(array $responses = []): void
    {
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    protected function graphUrl(string $path): string
    {
        return 'https://graph.threads.net/'.ltrim($path, '/');
    }

    /**
     * Fake `keyword_search`, answering per keyword (`q`); other keywords get an empty page.
     *
     * @param  array<string, PromiseInterface|Closure>  $responsesByKeyword
     */
    protected function fakeKeywordSearch(array $responsesByKeyword = []): void
    {
        $this->fakeThreads([
            $this->graphUrl('v1.0/keyword_search').'*' => function (Request $request) use ($responsesByKeyword) {
                $keyword = $this->requestParameters($request)['q'] ?? '';
                $response = $responsesByKeyword[$keyword] ?? $this->threadsSearchPage();

                return $response instanceof Closure ? $response($request) : $response;
            },
        ]);
    }

    /**
     * Query string and form parameters of a recorded outbound request.
     *
     * @return array<string, mixed>
     */
    protected function requestParameters(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        parse_str($request->body(), $body);

        return [...$query, ...$body];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function threadsSearchPage(array $items = []): PromiseInterface
    {
        return Http::response(['data' => $items]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function threadsItem(string $id, array $overrides = []): array
    {
        return [
            'id' => $id,
            'text' => 'Looking for a developer',
            'permalink' => 'https://www.threads.com/@alice/post/'.$id,
            'timestamp' => '2026-10-23T11:30:00+0000',
            'username' => 'alice',
            ...$overrides,
        ];
    }

    protected function threadsError(int $code, string $message = 'Something went wrong', int $status = 400): PromiseInterface
    {
        return Http::response([
            'error' => [
                'message' => $message,
                'type' => 'OAuthException',
                'code' => $code,
                'fbtrace_id' => 'trace123',
            ],
        ], $status);
    }

    /**
     * Build a Meta `signed_request` value.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function signedRequest(array $payload, ?string $secret = null): string
    {
        $encodedPayload = $this->base64UrlEncode((string) json_encode([
            'algorithm' => 'HMAC-SHA256',
            'issued_at' => 1_790_000_000,
            ...$payload,
        ]));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $secret ?? $this->threadsAppSecret, true),
        );

        return $signature.'.'.$encodedPayload;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Collect every log record written from now on, whatever the channel.
     *
     * @return \ArrayObject<int, MessageLogged>
     */
    protected function captureLogs(): \ArrayObject
    {
        $logs = new \ArrayObject;

        Event::listen(MessageLogged::class, function (MessageLogged $log) use ($logs): void {
            $logs[] = $log;
        });

        return $logs;
    }

    /**
     * @param  \ArrayObject<int, MessageLogged>  $logs
     * @return array<int, MessageLogged>
     */
    protected function logsAt(\ArrayObject $logs, string $level): array
    {
        return array_values(array_filter(
            $logs->getArrayCopy(),
            fn (MessageLogged $log): bool => $log->level === $level,
        ));
    }

    protected function logText(MessageLogged $log): string
    {
        return $log->message.' '.json_encode($this->flattenLogContext($log->context));
    }

    /**
     * Scalar values of a log record's context, as strings, plus its message.
     *
     * @return array<int, string>
     */
    protected function logValues(MessageLogged $log): array
    {
        return array_map(
            fn (mixed $value): string => (string) $value,
            [...array_values($this->flattenLogContext($log->context)), $log->message],
        );
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<string, mixed>
     */
    protected function flattenLogContext(array $context, string $prefix = ''): array
    {
        $flat = [];

        foreach ($context as $key => $value) {
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (is_array($value)) {
                $flat += $this->flattenLogContext($value, $prefix.$key.'.');
            } elseif (is_scalar($value) || $value === null) {
                $flat[$prefix.$key] = $value;
            } else {
                $flat[$prefix.$key] = get_debug_type($value);
            }
        }

        return $flat;
    }

    /**
     * Whether a number appears in a log record as a whole value or a whole number in the message.
     */
    protected function logMentionsNumber(MessageLogged $log, int|string $number): bool
    {
        $number = (string) $number;

        if (in_array($number, $this->logValues($log), true)) {
            return true;
        }

        return preg_match('/(?<!\d)'.preg_quote($number, '/').'(?!\d)/', $this->logText($log)) === 1;
    }

    /**
     * @param  \ArrayObject<int, MessageLogged>  $logs
     */
    protected function assertNoLogContains(\ArrayObject $logs, string $secret): void
    {
        foreach ($logs as $log) {
            $this->assertStringNotContainsString($secret, $this->logText($log));
        }
    }

    protected function createConnectedUser(): User
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create();

        return $user;
    }

    /**
     * Create a post of the user that is not tied to a keyword row.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makePost(User $user, array $attributes = []): ThreadsPost
    {
        return ThreadsPost::factory()->create([
            'user_id' => $user->id,
            'threads_keyword_id' => null,
            'keyword' => 'laravel',
            ...$attributes,
        ]);
    }

    /**
     * Create a keyword of the user.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeKeyword(User $user, string $keyword, array $attributes = []): ThreadsKeyword
    {
        return ThreadsKeyword::factory()->create([
            'user_id' => $user->id,
            'keyword' => $keyword,
            ...$attributes,
        ]);
    }

    /**
     * Put the user in the state that makes a manual run refuse for the given reason
     * (`not_connected`, `no_keywords`, `run_in_progress`, `cooldown`, `paused`, `budget`).
     * Expects the clock to be frozen by the caller.
     */
    protected function arrangeManualRunBlocker(User $user, string $reason): void
    {
        if ($reason !== 'not_connected') {
            ThreadsConnection::factory()->for($user)->create();
        }

        if ($reason !== 'no_keywords') {
            $this->makeKeyword($user, 'laravel');
        }

        match ($reason) {
            'run_in_progress' => ThreadsSearchRun::factory()->for($user)->queued()->create(),
            'cooldown' => ThreadsSearchRun::factory()->for($user)->manual()->create([
                'queued_at' => now()->subMinutes(5),
                'started_at' => now()->subMinutes(5),
                'finished_at' => now()->subMinutes(4),
            ]),
            'paused' => $user->threadsConnection()->update(['search_paused_until' => now()->addHour()]),
            'budget' => ThreadsSearchRun::factory()->for($user)->scheduled()->create([
                'queued_at' => now()->subHour(),
                'started_at' => now()->subHour(),
                'finished_at' => now()->subMinutes(59),
                'queries_sent' => 1999,
            ]),
            default => null,
        };

        if ($reason === 'budget') {
            $this->makeKeyword($user, 'php');
        }
    }
}
