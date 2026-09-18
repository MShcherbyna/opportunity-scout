<script setup lang="ts">
import { ThumbsUp } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const active = defineModel<boolean>('active', { required: true });

withDefaults(
    defineProps<{
        variant?: 'icon' | 'full';
    }>(),
    {
        variant: 'icon',
    },
);

function toggle() {
    active.value = !active.value;
}
</script>

<template>
    <Button
        type="button"
        :variant="active ? 'default' : 'outline'"
        :size="variant === 'icon' ? 'icon-sm' : 'sm'"
        :aria-pressed="active"
        :title="active ? 'Marked as relevant' : 'Mark as relevant'"
        @click="toggle"
    >
        <ThumbsUp class="size-3.5" :fill="active ? 'currentColor' : 'none'" />
        <span v-if="variant === 'full'">{{
            active ? 'Marked as relevant' : 'Mark as relevant'
        }}</span>
        <span v-else class="sr-only">{{
            active ? 'Marked as relevant' : 'Mark as relevant'
        }}</span>
    </Button>
</template>
