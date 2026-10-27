<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\UpdateReplyTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\UpdateReplyTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReplyTemplateController extends Controller
{
    /**
     * Show the Reply template settings tab.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('scout-settings/ReplyTemplate', [
            'replyTemplate' => $request->user()->reply_template,
        ]);
    }

    /**
     * Update the user's reply template.
     */
    public function update(UpdateReplyTemplateRequest $request, UpdateReplyTemplate $updateReplyTemplate): RedirectResponse
    {
        $updateReplyTemplate->handle($request->user(), $request->validated('reply_template'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reply template saved.']);

        return back(303);
    }
}
