import type { ComputedRef, Ref } from 'vue';
import { computed, onUnmounted, ref } from 'vue';
import { mockPosts } from '@/data/posts';
import type { Post, PostStatus } from '@/types';

export type PostSortOption = 'newest' | 'score';
export type PostStatusFilter = 'all' | PostStatus;

export type UsePostsOptions = {
    perPage?: number;
    initialStatusFilter?: PostStatusFilter;
};

export type UsePostsReturn = {
    isLoading: Ref<boolean>;
    statusFilter: Ref<PostStatusFilter>;
    sort: Ref<PostSortOption>;
    search: Ref<string>;
    page: Ref<number>;
    perPage: number;
    total: ComputedRef<number>;
    totalPages: ComputedRef<number>;
    posts: ComputedRef<Post[]>;
    hasActiveFilters: ComputedRef<boolean>;
    setPage: (next: number) => void;
    setStatusFilter: (next: PostStatusFilter) => void;
    setSort: (next: PostSortOption) => void;
    setSearch: (next: string) => void;
};

/**
 * Frontend-only feed of collected posts. Filtering, sorting and pagination
 * run entirely client-side over the mock dataset so the same composable can
 * later be swapped for a real API-backed data source without touching the
 * consuming components.
 */
export function usePosts(options: UsePostsOptions = {}): UsePostsReturn {
    const perPage = options.perPage ?? 10;

    const isLoading = ref(true);
    const statusFilter = ref<PostStatusFilter>(
        options.initialStatusFilter ?? 'all',
    );
    const sort = ref<PostSortOption>('newest');
    const search = ref('');
    const page = ref(1);

    let loadingTimeout: ReturnType<typeof setTimeout> | undefined;

    function simulateLoading() {
        isLoading.value = true;
        clearTimeout(loadingTimeout);
        loadingTimeout = setTimeout(() => {
            isLoading.value = false;
        }, 350);
    }

    onUnmounted(() => clearTimeout(loadingTimeout));

    simulateLoading();

    const filteredPosts = computed<Post[]>(() => {
        let posts = mockPosts;

        if (statusFilter.value !== 'all') {
            posts = posts.filter((post) => post.status === statusFilter.value);
        }

        const needle = search.value.trim().toLowerCase();

        if (needle) {
            posts = posts.filter(
                (post) =>
                    post.content.toLowerCase().includes(needle) ||
                    post.author.toLowerCase().includes(needle),
            );
        }

        return [...posts].sort((a, b) =>
            sort.value === 'score'
                ? b.relevanceScore - a.relevanceScore
                : new Date(b.publishedAt).getTime() -
                  new Date(a.publishedAt).getTime(),
        );
    });

    const total = computed(() => filteredPosts.value.length);
    const totalPages = computed(() =>
        Math.max(1, Math.ceil(total.value / perPage)),
    );

    const posts = computed<Post[]>(() => {
        const start = (page.value - 1) * perPage;

        return filteredPosts.value.slice(start, start + perPage);
    });

    const hasActiveFilters = computed(
        () => statusFilter.value !== 'all' || search.value.trim().length > 0,
    );

    function setPage(next: number) {
        page.value = Math.min(Math.max(1, next), totalPages.value);
    }

    function setStatusFilter(next: PostStatusFilter) {
        statusFilter.value = next;
        page.value = 1;
        simulateLoading();
    }

    function setSort(next: PostSortOption) {
        sort.value = next;
        page.value = 1;
        simulateLoading();
    }

    function setSearch(next: string) {
        search.value = next;
        page.value = 1;
        simulateLoading();
    }

    return {
        isLoading,
        statusFilter,
        sort,
        search,
        page,
        perPage,
        total,
        totalPages,
        posts,
        hasActiveFilters,
        setPage,
        setStatusFilter,
        setSort,
        setSearch,
    };
}
