<?php

namespace App\Http\Resources;

use App\Models\ThreadsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ThreadsPostPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{data: array<int, mixed>, currentPage: int, lastPage: int, perPage: int, total: int}
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, ThreadsPost> $page */
        $page = $this->resource;

        return [
            'data' => ThreadsPostResource::collection($page->getCollection())->resolve($request),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
