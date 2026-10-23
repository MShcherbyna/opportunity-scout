<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\DeleteThreadsDataForMeta;
use App\Actions\Threads\DisconnectThreadsAccountsForMeta;
use App\Http\Controllers\Controller;
use App\Services\Threads\InvalidSignedRequest;
use App\Services\Threads\SignedRequestParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaCallbackController extends Controller
{
    /**
     * Handle Meta's uninstall callback.
     */
    public function uninstall(Request $request, SignedRequestParser $parser, DisconnectThreadsAccountsForMeta $disconnectAccounts): Response
    {
        try {
            $threadsUserId = $parser->parse($this->signedRequestFrom($request));
        } catch (InvalidSignedRequest $exception) {
            return $this->reject($exception);
        }

        $matched = $disconnectAccounts->handle($threadsUserId);

        Log::info('Threads uninstall callback processed.', ['user_id' => $threadsUserId, 'matched_connections' => $matched]);

        return response('', 200);
    }

    /**
     * Handle Meta's data deletion callback.
     */
    public function dataDeletion(Request $request, SignedRequestParser $parser, DeleteThreadsDataForMeta $deleteData): JsonResponse|Response
    {
        try {
            $threadsUserId = $parser->parse($this->signedRequestFrom($request));
        } catch (InvalidSignedRequest $exception) {
            return $this->reject($exception);
        }

        $deletionRequest = $deleteData->handle($threadsUserId);

        return response()->json([
            'url' => url('/data-deletion').'?code='.$deletionRequest->confirmation_code,
            'confirmation_code' => $deletionRequest->confirmation_code,
        ]);
    }

    private function signedRequestFrom(Request $request): ?string
    {
        $value = $request->input('signed_request');

        return is_string($value) ? $value : null;
    }

    private function reject(InvalidSignedRequest $exception): Response
    {
        Log::warning('Rejected Meta signed request.', ['reason' => $exception->getMessage()]);

        return response('', 400);
    }
}
