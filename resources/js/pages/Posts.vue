<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import PostsFilters from '@/components/posts/PostsFilters.vue';
import PostsTable from '@/components/posts/PostsTable.vue';
import SearchStatusLine from '@/components/posts/SearchStatusLine.vue';
import RunScoutButton from '@/components/RunScoutButton.vue';
import { Button } from '@/components/ui/button';
import { useSearchRunPolling } from '@/composables/useSearchRunPolling';
import { formatDateTime } from '@/lib/threadsCopy';
import postsRoutes from '@/routes/posts';
import { scraping } from '@/routes/settings';
import { connect } from '@/routes/threads';
import type { PaginatedPosts, PostFilters, SearchStatus } from '@/types';

const props = defineProps<{
    posts: PaginatedPosts;
    filters: PostFilters;
    keywordOptions: string[];
    keywordCount: number;
    searchStatus: SearchStatus;
    ownPostsOnly: boolean;
}>();

defineOptions({
    layout: {
        title: 'Posts',
        description: 'All collected Threads posts',
    },
});

const page = usePage();

const connectionStatus = computed(
    () => page.props.threadsConnection?.status ?? null,
);
const isConnected = computed(() => connectionStatus.value === 'connected');
const hasConnection = computed(() => connectionStatus.value !== null);

const search = ref(props.filters.search);
const isLoading = ref(false);

const hasActiveFilters = computed(
    () => !!props.filters.search || !!props.filters.keyword,
);

function visit(params: {
    search?: string;
    keyword?: string | null;
    page?: number;
}) {
    router.get(
        postsRoutes.index(),
        {
            search: params.search || undefined,
            keyword: params.keyword || undefined,
            page: params.page && params.page > 1 ? params.page : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isLoading.value = true;
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}

const visitForSearch = useDebounceFn((value: string) => {
    visit({ search: value, keyword: props.filters.keyword });
}, 300);

watch(search, (value) => {
    if (value !== props.filters.search) {
        void visitForSearch(value);
    }
});

watch(
    () => props.filters.search,
    (value) => {
        if (value !== search.value) {
            search.value = value;
        }
    },
);

function setKeyword(value: string | null) {
    visit({ search: search.value, keyword: value });
}

function setPage(value: number) {
    visit({
        search: props.filters.search,
        keyword: props.filters.keyword,
        page: value,
    });
}

function clearFilters() {
    search.value = '';
    visit({});
}

useSearchRunPolling(() => props.searchStatus, ['searchStatus', 'posts']);
</script>

<template>
    <Head title="Posts" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <SearchStatusLine
                    v-if="hasConnection"
                    :search-status="searchStatus"
                    :is-disconnected="!isConnected"
                />
                <p v-if="ownPostsOnly" class="text-muted-foreground text-xs">
                    Until Meta approves search access, results include only your
                    own Threads posts.
                </p>
            </div>
            <RunScoutButton :search-status="searchStatus" />
        </div>

        <div class="border-border bg-card rounded-lg border">
            <div
                class="border-border flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-sm font-semibold">All posts</h2>
                <PostsFilters
                    v-model:search="search"
                    :keyword="filters.keyword"
                    :keyword-options="keywordOptions"
                    @update:keyword="setKeyword"
                />
            </div>

            <PostsTable :posts="posts.data" :is-loading="isLoading">
                <template #empty>
                    <EmptyState
                        v-if="hasActiveFilters"
                        title="No matching posts"
                        description="No posts match your search or keyword filter."
                    >
                        <template #action>
                            <Button variant="outline" @click="clearFilters">
                                Clear filters
                            </Button>
                        </template>
                    </EmptyState>
                    <EmptyState
                        v-else-if="!isConnected"
                        title="Connect Threads to start"
                        description="Opportunity Scout searches Threads on your behalf. Connect your account to start finding posts."
                    >
                        <template #action>
                            <Button @click="router.post(connect())">
                                Connect Threads
                            </Button>
                        </template>
                    </EmptyState>
                    <EmptyState
                        v-else-if="keywordCount === 0"
                        title="Add keywords to start"
                        description="Add the words people use when they post work requests."
                    >
                        <template #action>
                            <Button as-child>
                                <Link :href="scraping()">Add keywords</Link>
                            </Button>
                        </template>
                    </EmptyState>
                    <EmptyState
                        v-else
                        title="No posts yet"
                        :description="`Opportunity Scout hasn't found any posts yet. Last search: ${
                            searchStatus.lastRunFinishedAt
                                ? formatDateTime(searchStatus.lastRunFinishedAt)
                                : 'not run yet'
                        }.`"
                    />
                </template>
            </PostsTable>

            <div
                v-if="!isLoading && posts.data.length > 0"
                class="border-border border-t p-4"
            >
                <Pagination
                    :current-page="posts.currentPage"
                    :total-pages="posts.lastPage"
                    :total-items="posts.total"
                    :per-page="posts.perPage"
                    @update:current-page="setPage"
                />
            </div>
        </div>
    </div>
</template>
