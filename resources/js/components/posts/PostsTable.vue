<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SquareArrowOutUpRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatRelativeTime } from '@/lib/threadsCopy';
import postsRoutes from '@/routes/posts';
import type { ThreadsPost } from '@/types';

withDefaults(
    defineProps<{
        posts: ThreadsPost[];
        isLoading?: boolean;
        /** `full` — the Posts page table. `dashboard` — the compact latest-posts widget. */
        variant?: 'full' | 'dashboard';
    }>(),
    {
        isLoading: false,
        variant: 'full',
    },
);

function excerpt(post: ThreadsPost): string {
    if (!post.text) {
        return '(no text)';
    }

    return post.text.length > 140
        ? `${post.text.slice(0, 140).trimEnd()}…`
        : post.text;
}
</script>

<template>
    <div>
        <TableSkeleton v-if="isLoading" :columns="variant === 'full' ? 5 : 3" />

        <template v-else-if="posts.length === 0">
            <slot name="empty">
                <EmptyState
                    title="No posts yet"
                    description="Opportunity Scout hasn't found any posts yet."
                />
            </slot>
        </template>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Post</TableHead>
                    <TableHead>Author</TableHead>
                    <TableHead v-if="variant === 'full'">Keyword</TableHead>
                    <TableHead v-if="variant === 'full'">Posted</TableHead>
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
                            class="hover:text-primary text-sm font-semibold underline-offset-2 hover:underline"
                            :class="{ 'text-muted-foreground': !post.text }"
                        >
                            {{ excerpt(post) }}
                        </Link>
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        @{{ post.username }}
                    </TableCell>
                    <TableCell v-if="variant === 'full'">
                        <Badge variant="secondary">{{ post.keyword }}</Badge>
                    </TableCell>
                    <TableCell
                        v-if="variant === 'full'"
                        class="text-muted-foreground text-sm"
                    >
                        <time
                            :datetime="post.postedAt"
                            :title="formatDateTime(post.postedAt)"
                        >
                            {{ formatRelativeTime(post.postedAt) }}
                        </time>
                    </TableCell>
                    <TableCell class="text-muted-foreground text-sm">
                        <time
                            :datetime="post.foundAt"
                            :title="formatDateTime(post.foundAt)"
                        >
                            {{ formatRelativeTime(post.foundAt) }}
                        </time>
                    </TableCell>
                    <TableCell class="text-right">
                        <Button
                            v-if="post.permalink"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <a
                                :href="post.permalink"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open in Threads
                                <SquareArrowOutUpRight class="size-3.5" />
                            </a>
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
