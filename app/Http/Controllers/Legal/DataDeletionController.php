<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Models\ThreadsDataDeletionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataDeletionController extends Controller
{
    /**
     * Show the data deletion instructions and request status.
     */
    public function show(Request $request): Response
    {
        $code = $request->query('code');

        if ($code === null || $code === '') {
            return Inertia::render('legal/DataDeletion', [
                'deletionRequest' => null,
                'codeNotFound' => false,
            ]);
        }

        $deletionRequest = is_string($code) && preg_match('/\A[A-Za-z0-9]{1,32}\z/', $code) === 1
            ? ThreadsDataDeletionRequest::query()->where('confirmation_code', $code)->first()
            : null;

        return Inertia::render('legal/DataDeletion', [
            'deletionRequest' => $deletionRequest === null ? null : [
                'confirmationCode' => $deletionRequest->confirmation_code,
                'status' => $deletionRequest->status->value,
                'requestedAt' => $deletionRequest->requested_at->toIso8601String(),
                'completedAt' => $deletionRequest->completed_at?->toIso8601String(),
            ],
            'codeNotFound' => $deletionRequest === null,
        ]);
    }
}
