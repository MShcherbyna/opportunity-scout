<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, SquareArrowOutUpRight } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatRelativeTime } from '@/lib/threadsCopy';
import postsRoutes from '@/routes/posts';
import type { ThreadsPost } from '@/types';

defineProps<{
    post: ThreadsPost;
}>();

defineOptions({
    layout: {
        title: 'Post details',
    },
});
</script>

<template>
    <Head title="Post details" />

    <div class="flex flex-1 flex-col p-4 md:p-6">
        <div class="mb-4 flex items-center justify-between">
            <Button as-child variant="outline" size="sm">
                <Link :href="postsRoutes.index()">
                    <ArrowLeft class="size-3.5" />
                    Back to posts
                </Link>
            </Button>
        </div>

        <div class="border-border bg-card max-w-3xl rounded-lg border p-5">
            <p class="text-muted-foreground text-xs">
                @{{ post.username }} ·
                <time
                    :datetime="post.postedAt"
                    :title="formatDateTime(post.postedAt)"
                >
                    {{ formatRelativeTime(post.postedAt) }}
                </time>
            </p>

            <p
                class="mt-3 text-base leading-relaxed whitespace-pre-line"
                :class="{ 'text-muted-foreground': !post.text }"
            >
                {{ post.text ?? '(no text)' }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <Badge variant="secondary">{{ post.keyword }}</Badge>
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
            </div>
        </div>
    </div>
</template>
