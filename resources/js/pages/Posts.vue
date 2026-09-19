<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import PostsFilters from '@/components/posts/PostsFilters.vue';
import PostsTable from '@/components/posts/PostsTable.vue';
import RunScoutButton from '@/components/RunScoutButton.vue';
import { usePosts } from '@/composables/usePosts';

defineOptions({
    layout: {
        title: 'Posts',
        description: 'All collected Threads posts',
    },
});

const {
    posts,
    isLoading,
    statusFilter,
    sort,
    search,
    page,
    totalPages,
    total,
    perPage,
    hasActiveFilters,
    setPage,
    setStatusFilter,
    setSort,
    setSearch,
} = usePosts({ perPage: 10 });
</script>

<template>
    <Head title="Posts" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex justify-end">
            <RunScoutButton />
        </div>

        <div class="border-border bg-card rounded-lg border">
            <div
                class="border-border flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold">All posts</h2>
                    <p class="text-muted-foreground text-xs">
                        Filtered and scored by AI
                    </p>
                </div>
                <PostsFilters
                    :status="statusFilter"
                    :sort="sort"
                    :search="search"
                    @update:status="setStatusFilter"
                    @update:sort="setSort"
                    @update:search="setSearch"
                />
            </div>

            <PostsTable
                :posts="posts"
                :is-loading="isLoading"
                :has-active-filters="hasActiveFilters"
            />

            <div
                v-if="!isLoading && posts.length > 0"
                class="border-border border-t p-4"
            >
                <Pagination
                    :current-page="page"
                    :total-pages="totalPages"
                    :total-items="total"
                    :per-page="perPage"
                    @update:current-page="setPage"
                />
            </div>
        </div>
    </div>
</template>
