<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SquareArrowOutUpRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import MarkRelevantButton from '@/components/MarkRelevantButton.vue';
import PostStatusBadge from '@/components/PostStatusBadge.vue';
import RelevanceScore from '@/components/RelevanceScore.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { initialsFromHandle } from '@/lib/utils';
import postsRoutes from '@/routes/posts';
import type { Post } from '@/types';

const props = withDefaults(
    defineProps<{
        posts: Post[];
        isLoading?: boolean;
        /** `full` — the Posts page table. `dashboard` — the compact latest-posts widget. */
        variant?: 'full' | 'dashboard';
        hasActiveFilters?: boolean;
    }>(),
    {
        isLoading: false,
        variant: 'full',
        hasActiveFilters: false,
    },
);

const showStatus = props.variant === 'full';

function truncate(text: string, length: number): string {
    return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
}

function postTitle(post: Post): string {
    return truncate(post.content, 46);
}

function postExcerpt(post: Post): string {
    if (post.content.length <= 46) {
        return '';
    }

    return truncate(post.content.slice(46).trimStart(), 50);
}
</script>

<template>
    <div>
        <TableSkeleton v-if="isLoading" :columns="showStatus ? 5 : 4" />

        <EmptyState
            v-else-if="posts.length === 0"
            :title="hasActiveFilters ? 'No matching posts' : 'No posts yet'"
            :description="
                hasActiveFilters
                    ? 'No posts match your current filters. Try a different status or search term.'
                    : 'Opportunity Scout hasn’t collected anything yet. Check your keywords and scraping frequency, then wait for the next run.'
            "
        />

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Post</TableHead>
                    <TableHead>Author</TableHead>
                    <TableHead v-if="showStatus">Status</TableHead>
                    <TableHead>Score</TableHead>
                    <TableHead>Found</TableHead>
                    <TableHead class="text-right">
                        <span class="sr-only">Action</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="post in posts" :key="post.id">
                    <TableCell class="max-w-xs whitespace-normal">
                        <Link
                            :href="postsRoutes.show(post.id)"
                            class="group flex items-center gap-2.5"
                        >
                            <div
                                v-if="variant === 'dashboard'"
                                class="bg-secondary text-muted-foreground border-border flex size-7 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold"
                            >
                                {{ initialsFromHandle(post.author) }}
                            </div>
                            <div class="min-w-0">
                                <div
                                    class="group-hover:text-primary text-sm font-semibold underline-offset-2 group-hover:underline"
                                >
                                    {{ postTitle(post) }}
                                </div>
                                <div
                                    class="text-muted-foreground mt-0.5 text-xs"
                                >
                                    {{
                                        variant === 'dashboard'
                                            ? postExcerpt(post)
                                            : 'Remote · contract opportunity'
                                    }}
                                </div>
                            </div>
                        </Link>
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        {{ post.author }}
                    </TableCell>
                    <TableCell v-if="showStatus">
                        <PostStatusBadge :status="post.status" />
                    </TableCell>
                    <TableCell>
                        <RelevanceScore
                            :score="post.relevanceScore"
                            :variant="
                                variant === 'dashboard' ? 'green' : 'plain'
                            "
                        />
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        {{ post.foundAt }}
                    </TableCell>
                    <TableCell class="text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <MarkRelevantButton
                                v-model:active="post.manuallyMarkedRelevant"
                            />
                            <Button
                                v-if="variant === 'dashboard'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <a
                                    :href="post.threadsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Open in Threads"
                                >
                                    <SquareArrowOutUpRight class="size-3.5" />
                                    <span class="sr-only">Open in Threads</span>
                                </a>
                            </Button>
                            <Button v-else variant="outline" size="sm" as-child>
                                <a
                                    :href="post.threadsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Threads
                                    <SquareArrowOutUpRight class="size-3.5" />
                                </a>
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
