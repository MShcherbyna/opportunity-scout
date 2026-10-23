<?php

namespace App\Services\Threads;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ThreadsClient
{
    private const RATE_LIMIT_CODES = [4, 17, 32, 341, 613];

    /**
     * Exchange an authorization code for a short-lived token (TA-2).
     *
     * @return array{access_token: string, user_id: string}
     */
    public function exchangeCode(string $code): array
    {
        $data = $this->send('oauth/access_token', [
            'client_id' => config('services.threads.client_id'),
            'client_secret' => config('services.threads.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.threads.redirect_uri'),
        ], 'POST', [$code]);

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'user_id' => (string) ($data['user_id'] ?? ''),
        ];
    }

    /**
     * Exchange a short-lived token for a long-lived token (TA-3).
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $data = $this->send('access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => config('services.threads.client_secret'),
            'access_token' => $shortLivedToken,
        ], 'GET', [$shortLivedToken]);

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'expires_in' => (int) ($data['expires_in'] ?? 0),
        ];
    }

    /**
     * Fetch the profile of the token owner (TA-4).
     *
     * @return array{id: string, username: string, name: string|null}
     */
    public function me(string $accessToken): array
    {
        $data = $this->send('v1.0/me', [
            'fields' => 'id,username,name',
            'access_token' => $accessToken,
        ], 'GET', [$accessToken]);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'username' => (string) ($data['username'] ?? ''),
            'name' => isset($data['name']) ? (string) $data['name'] : null,
        ];
    }

    /**
     * Refresh a long-lived token (TA-5).
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshToken(string $accessToken): array
    {
        $data = $this->send('refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $accessToken,
        ], 'GET', [$accessToken]);

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'expires_in' => (int) ($data['expires_in'] ?? 0),
        ];
    }

    /**
     * Search recent posts for a keyword (TA-6); returns the `data` array only.
     *
     * @return array<int, array{id?: string, text?: string, permalink?: string, timestamp?: string, username?: string}>
     */
    public function keywordSearch(string $accessToken, string $keyword, int $since, int $until): array
    {
        $data = $this->send('v1.0/keyword_search', [
            'q' => $keyword,
            'search_type' => 'RECENT',
            'limit' => 100,
            'since' => $since,
            'until' => $until,
            'fields' => 'id,text,permalink,timestamp,username',
            'access_token' => $accessToken,
        ], 'GET', [$accessToken]);

        $items = $data['data'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * Send one request and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<int, string>  $secrets  values that must never reach exceptions or logs
     * @return array<string, mixed>
     *
     * @throws ThreadsApiException
     */
    private function send(string $path, array $parameters, string $method, array $secrets): array
    {
        $secrets = array_values(array_filter([...$secrets, (string) config('services.threads.client_secret')]));

        try {
            $response = $this->request($method === 'POST')->send($method, $path, [
                $method === 'POST' ? 'form_params' : 'query' => $parameters,
            ]);
        } catch (ConnectionException) {
            throw new ThreadsApiException(ThreadsErrorKind::Transient);
        }

        if ($response->failed()) {
            throw $this->exceptionFor($response, $secrets);
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    private function request(bool $asForm): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.threads.graph_url'), '/').'/')
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5);

        return $asForm ? $request->asForm() : $request;
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function exceptionFor(Response $response, array $secrets): ThreadsApiException
    {
        $status = $response->status();
        $body = $response->json();
        $error = is_array($body) ? ($body['error'] ?? $body) : [];
        $error = is_array($error) ? $error : [];

        $code = $error['code'] ?? null;
        $code = is_numeric($code) ? (int) $code : null;
        $message = (string) ($error['message'] ?? $error['error_message'] ?? '');

        $kind = match (true) {
            $code === 190 => ThreadsErrorKind::TokenInvalid,
            in_array($code, self::RATE_LIMIT_CODES, true),
            $status === 429,
            Str::contains($message, 'limit', ignoreCase: true) => ThreadsErrorKind::RateLimited,
            $status >= 500 => ThreadsErrorKind::Transient,
            default => ThreadsErrorKind::Other,
        };

        return new ThreadsApiException($kind, $status, $code, $this->sanitize($response->body(), $secrets));
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function sanitize(string $body, array $secrets): string
    {
        $body = str_replace($secrets, '[redacted]', $body);
        $body = (string) preg_replace('/(access_token|client_secret)=[^&\s"]+/i', '$1=[redacted]', $body);

        return Str::limit($body, 500, '');
    }
}
