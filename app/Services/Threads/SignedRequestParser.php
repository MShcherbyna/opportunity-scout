<?php

namespace App\Services\Threads;

class SignedRequestParser
{
    private const MAX_LENGTH = 8192;

    /**
     * Verify a Meta signed request and return the Threads user id.
     *
     * @throws InvalidSignedRequest
     */
    public function parse(?string $signedRequest): string
    {
        if ($signedRequest === null || $signedRequest === '') {
            throw new InvalidSignedRequest('The signed request is missing.');
        }

        if (strlen($signedRequest) > self::MAX_LENGTH) {
            throw new InvalidSignedRequest('The signed request is too long.');
        }

        $parts = explode('.', $signedRequest);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidSignedRequest('The signed request is malformed.');
        }

        [$encodedSignature, $encodedPayload] = $parts;

        $signature = $this->decode($encodedSignature);
        $payloadJson = $this->decode($encodedPayload);

        if ($signature === null || $payloadJson === null) {
            throw new InvalidSignedRequest('The signed request is malformed.');
        }

        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            throw new InvalidSignedRequest('The signed request payload is malformed.');
        }

        $algorithm = $payload['algorithm'] ?? null;

        if (! is_string($algorithm) || strcasecmp($algorithm, 'HMAC-SHA256') !== 0) {
            throw new InvalidSignedRequest('The signed request uses an unsupported algorithm.');
        }

        $secret = (string) config('services.threads.client_secret');

        if ($secret === '') {
            throw new InvalidSignedRequest('The signed request signature is invalid.');
        }

        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidSignedRequest('The signed request signature is invalid.');
        }

        $userId = $payload['user_id'] ?? null;

        if ((! is_string($userId) && ! is_int($userId)) || (string) $userId === '') {
            throw new InvalidSignedRequest('The signed request has no user id.');
        }

        return (string) $userId;
    }

    private function decode(string $value): ?string
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }
}
