<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\SetPostRelevance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\UpdatePostRelevanceRequest;
use Illuminate\Http\RedirectResponse;

class PostRelevanceController extends Controller
{
    /**
     * Set a found post's relevance flag.
     */
    public function update(UpdatePostRelevanceRequest $request, int $post, SetPostRelevance $setPostRelevance): RedirectResponse
    {
        $found = $request->user()->threadsPosts()->findOrFail($post);

        $setPostRelevance->handle($found, $request->isRelevant());

        return back(303);
    }
}
