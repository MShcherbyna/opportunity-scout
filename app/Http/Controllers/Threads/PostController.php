<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\PostIndexRequest;
use App\Http\Resources\ThreadsPostResource;
use App\Services\Threads\SearchStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Show the list of found posts.
     */
    public function index(PostIndexRequest $request, SearchStatus $searchStatus): Response
    {
        $user = $request->user();
        $search = (string) ($request->validated('search') ?? '');
        $keyword = $request->validated('keyword');

        return Inertia::render('Posts', [
            'posts' => function () use ($request, $user, $search, $keyword): array {
                $query = $user->threadsPosts()
                    ->when($search !== '', function ($query) use ($search): void {
                        $term = '%'.addcslashes($search, '\\%_').'%';
                        $query->where(fn ($query) => $query->where('text', 'like', $term)->orWhere('username', 'like', $term));
                    })
                    ->when($keyword !== null, fn ($query) => $query->where('keyword', $keyword));

                $total = (clone $query)->count();
                $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
                $page = min((int) ($request->validated('page') ?? 1), $lastPage);
                $posts = $query->orderByDesc('posted_at')->orderByDesc('id')->forPage($page, self::PER_PAGE)->get();

                return [
                    'data' => ThreadsPostResource::collection($posts)->resolve($request),
                    'currentPage' => $page,
                    'lastPage' => $lastPage,
                    'perPage' => self::PER_PAGE,
                    'total' => $total,
                ];
            },
            'filters' => fn (): array => ['search' => $search, 'keyword' => $keyword],
            'keywordOptions' => fn (): array => $user->threadsKeywords()->orderBy('id')->pluck('keyword')->all(),
            'keywordCount' => fn (): int => $user->threadsKeywords()->count(),
            'searchStatus' => fn (): array => $searchStatus->for($user),
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
