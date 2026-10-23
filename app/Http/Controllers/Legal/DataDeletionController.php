<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataDeletionRequestResource;
use App\Services\Threads\DataDeletionLookup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataDeletionController extends Controller
{
    /**
     * Show the data deletion instructions and request status.
     */
    public function show(Request $request, DataDeletionLookup $dataDeletionLookup): Response
    {
        $result = $dataDeletionLookup->lookup($request->query('code'));

        return Inertia::render('legal/DataDeletion', [
            'deletionRequest' => $result['deletionRequest'] === null ? null : (new DataDeletionRequestResource($result['deletionRequest']))->resolve($request),
            'codeNotFound' => $result['codeNotFound'],
        ]);
    }
}
