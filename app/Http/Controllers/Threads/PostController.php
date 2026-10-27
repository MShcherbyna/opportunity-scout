<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\PostIndexRequest;
use App\Http\Resources\SearchStatusResource;
use App\Http\Resources\ThreadsPostPageResource;
use App\Http\Resources\ThreadsPostResource;
use App\Services\Threads\PostListing;
use App\Services\Threads\SearchStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Show the list of found posts.
     */
    public function index(PostIndexRequest $request, PostListing $postListing, SearchStatus $searchStatus): Response
    {
        $user = $request->user();

        return Inertia::render('Posts', [
            'posts' => fn (): array => (new ThreadsPostPageResource(
                $postListing->paginate($user, $request->search(), $request->keyword(), $request->page())
            ))->resolve($request),
            'filters' => fn (): array => $request->filters(),
            'keywordOptions' => fn (): array => $postListing->keywordOptions($user),
            'keywordCount' => fn (): int => $user->threadsKeywords()->count(),
            'searchStatus' => fn (): array => (new SearchStatusResource($searchStatus->snapshot($user)))->resolve($request),
            'ownPostsOnly' => fn (): bool => (bool) config('services.threads.own_posts_only'),
        ]);
    }

    /**
     * Show one found post.
     */
    public function show(Request $request, int $post): Response
    {
        $found = $request->user()->threadsPosts()->findOrFail($post);

        return Inertia::render('PostDetail', [
            'post' => (new ThreadsPostResource($found))->resolve($request),
        ]);
    }
}
