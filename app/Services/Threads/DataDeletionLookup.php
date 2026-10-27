<?php

namespace App\Services\Threads;

use App\Models\ThreadsDataDeletionRequest;

class DataDeletionLookup
{
    /**
     * Find the deletion request for a confirmation code from the query string.
     *
     * An empty code is not an error; a malformed code is not looked up at all.
     *
     * @return array{deletionRequest: ThreadsDataDeletionRequest|null, codeNotFound: bool}
     */
    public function lookup(mixed $code): array
    {
        if ($code === null || $code === '') {
            return ['deletionRequest' => null, 'codeNotFound' => false];
        }

        $deletionRequest = is_string($code) && preg_match('/\A[A-Za-z0-9]{1,32}\z/', $code) === 1
            ? ThreadsDataDeletionRequest::query()->where('confirmation_code', $code)->first()
            : null;

        return ['deletionRequest' => $deletionRequest, 'codeNotFound' => $deletionRequest === null];
    }
}
