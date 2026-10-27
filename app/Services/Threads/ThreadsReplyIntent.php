<?php

namespace App\Services\Threads;

class ThreadsReplyIntent
{
    /**
     * The Threads reply-intent endpoint (AC-116).
     */
    public const BASE_URL = 'https://www.threads.com/intent/post';

    /**
     * Build the "Reply in Threads" intent URL for a post's permalink.
     *
     * Returns null when there is no permalink, or when the permalink's path
     * has no usable (non-empty) last segment to use as the reply shortcode
     * (SPEC-01 Post shape, UI-5, Q2). Both the shortcode and the optional
     * saved reply template are percent-encoded per RFC 3986 (AC-116).
     */
    public function url(?string $permalink, ?string $replyTemplate): ?string
    {
        if ($permalink === null) {
            return null;
        }

        $path = parse_url($permalink, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));
        $shortcode = end($segments);

        if ($shortcode === false) {
            return null;
        }

        $query = 'reply_post_shortcode='.rawurlencode(rawurldecode($shortcode));

        if ($replyTemplate !== null && $replyTemplate !== '') {
            $query .= '&text='.rawurlencode($replyTemplate);
        }

        return self::BASE_URL.'?'.$query;
    }
}
