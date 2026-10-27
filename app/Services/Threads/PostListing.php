<?php

namespace App\Services\Threads;

use App\Models\ThreadsPost;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class PostListing
{
    public const PER_PAGE = 10;

    /**
     * One page of the user's posts, relevant-marked first then newest post first; a page past the
     * end is clamped to the last page. The search uses an explicit `escape '!'` because SQLite's
     * LIKE has no default escape character.
     *
     * @return LengthAwarePaginator<int, ThreadsPost>
     */
    public function paginate(User $user, string $search, ?string $keyword, int $page): LengthAwarePaginator
    {
        $query = $user->threadsPosts()
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($query) => $query
                    ->whereRaw("text like ? escape '!'", [$term])
                    ->orWhereRaw("username like ? escape '!'", [$term]));
            })
            ->when($keyword !== null, fn ($query) => $query->where('keyword', $keyword));

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);
        $posts = $query->orderByDesc('is_relevant')->orderByDesc('posted_at')->orderByDesc('id')->forPage($page, self::PER_PAGE)->get();

        return new LengthAwarePaginator($posts, $total, self::PER_PAGE, $page);
    }

    /**
     * The user's keywords in creation order, for the filter dropdown.
     *
     * @return list<string>
     */
    public function keywordOptions(User $user): array
    {
        return array_values(
            $user->threadsKeywords()->orderBy('id')->pluck('keyword')->map(fn (mixed $keyword): string => $keyword)->all()
        );
    }
}
