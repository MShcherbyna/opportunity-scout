<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MarkRelevantButton from '@/components/MarkRelevantButton.vue';
import RelevanceScore from '@/components/RelevanceScore.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/composables/useClipboard';
import { findPostById } from '@/data/posts';
import postsRoutes from '@/routes/posts';

const props = defineProps<{
    postId: number;
}>();

const post = computed(() => findPostById(props.postId));

defineOptions({
    layout: {
        title: 'Post details',
    },
});

const { copied, copy } = useClipboard();

const response = computed({
    get: () => post.value?.suggestedResponse ?? '',
    set: () => {
        // Editable in the UI only; not persisted anywhere in this mock.
    },
});
</script>

<template>
    <Head title="Post details" />

    <div class="flex flex-1 flex-col p-4 md:p-6">
        <EmptyState
            v-if="!post"
            title="Post not found"
            description="This post may have been removed from the collected feed, or the link is incorrect."
        >
            <template #action>
                <Button as-child variant="outline">
                    <Link :href="postsRoutes.index()">Back to posts</Link>
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <div class="mb-4 flex items-center justify-between">
                <Button as-child variant="outline" size="sm">
                    <Link :href="postsRoutes.index()">
                        <ArrowLeft class="size-3.5" />
                        Back to posts
                    </Link>
                </Button>
                <MarkRelevantButton
                    v-model:active="post.manuallyMarkedRelevant"
                    variant="full"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
                <div class="border-border bg-card rounded-lg border p-5">
                    <RelevanceScore
                        :score="post.relevanceScore"
                        label="relevant"
                    />
                    <h1 class="mt-3 text-xl leading-snug font-semibold">
                        {{ post.content }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-xs">
                        <a
                            :href="post.threadsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-foreground hover:underline"
                        >
                            {{ post.author }}
                        </a>
                        · {{ post.foundAt }}
                    </p>

                    <div class="border-border mt-4 border-t pt-5">
                        <h2 class="text-sm font-semibold">
                            Why AI marked it relevant
                        </h2>
                        <ul
                            class="text-muted-foreground mt-3 list-disc space-y-1.5 pl-5 text-sm"
                        >
                            <li
                                v-for="reason in post.relevanceReasons"
                                :key="reason"
                            >
                                {{ reason }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-border bg-card h-fit rounded-lg border p-5">
                    <h2 class="text-sm font-semibold">Suggested response</h2>
                    <p class="text-muted-foreground text-xs">
                        Generated from your response template
                    </p>

                    <label for="suggested-response" class="sr-only"
                        >Suggested response</label
                    >
                    <Textarea
                        id="suggested-response"
                        v-model="response"
                        class="mt-3 min-h-32"
                    />

                    <Button
                        class="mt-3 w-full"
                        :disabled="!response.trim()"
                        @click="copy(response)"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                        {{ copied ? 'Copied' : 'Copy response' }}
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
