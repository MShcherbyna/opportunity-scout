<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/posts/relevant';

const props = withDefaults(
    defineProps<{
        postId: number;
        isRelevant: boolean;
        variant?: 'icon' | 'full';
    }>(),
    {
        variant: 'icon',
    },
);

const processing = ref(false);

function toggle() {
    router.patch(
        update.url(props.postId),
        { is_relevant: !props.isRelevant },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Button
        type="button"
        :variant="isRelevant ? 'default' : 'outline'"
        :size="variant === 'icon' ? 'icon-sm' : 'sm'"
        :aria-pressed="isRelevant"
        :disabled="processing"
        :title="isRelevant ? 'Marked as relevant' : 'Mark as relevant'"
        @click="toggle"
    >
        <Heart class="size-3.5" :fill="isRelevant ? 'currentColor' : 'none'" />
        <span v-if="variant === 'full'">{{
            isRelevant ? 'Marked as relevant' : 'Mark as relevant'
        }}</span>
        <span v-else class="sr-only">{{
            isRelevant ? 'Marked as relevant' : 'Mark as relevant'
        }}</span>
    </Button>
</template>
