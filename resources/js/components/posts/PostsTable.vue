<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SquareArrowOutUpRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
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
import postsRoutes from '@/routes/posts';
import type { Post } from '@/types';

withDefaults(
    defineProps<{
        posts: Post[];
        isLoading?: boolean;
        showStatus?: boolean;
        hasActiveFilters?: boolean;
    }>(),
    {
        isLoading: false,
        showStatus: true,
        hasActiveFilters: false,
    },
);
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
                    : 'Threads Scraper hasn’t collected anything yet. Check your keywords and scraping frequency, then wait for the next run.'
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
                            class="hover:text-primary line-clamp-2 text-sm font-medium underline-offset-2 hover:underline"
                        >
                            {{ post.content }}
                        </Link>
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        {{ post.author }}
                    </TableCell>
                    <TableCell v-if="showStatus">
                        <PostStatusBadge :status="post.status" />
                    </TableCell>
                    <TableCell>
                        <RelevanceScore :score="post.relevanceScore" />
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        {{ post.foundAt }}
                    </TableCell>
                    <TableCell class="text-right">
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="post.threadsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Threads
                                <SquareArrowOutUpRight class="size-3.5" />
                            </a>
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
