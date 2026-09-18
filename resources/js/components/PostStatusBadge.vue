<script setup lang="ts">
import { CheckCircle2, CircleHelp, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { PostStatus } from '@/types';

const props = defineProps<{
    status: PostStatus;
}>();

const config = computed(() => {
    switch (props.status) {
        case 'relevant':
            return {
                label: 'Relevant',
                icon: CheckCircle2,
                class: 'border-transparent bg-primary/10 text-primary dark:bg-primary/15',
            };
        case 'maybe':
            return {
                label: 'Maybe',
                icon: CircleHelp,
                class: 'border-transparent bg-amber-500/10 text-amber-600 dark:text-amber-400',
            };
        case 'rejected':
        default:
            return {
                label: 'Rejected',
                icon: XCircle,
                class: 'border-transparent bg-muted text-muted-foreground',
            };
    }
});
</script>

<template>
    <Badge variant="outline" :class="config.class">
        <component :is="config.icon" class="size-3" />
        {{ config.label }}
    </Badge>
</template>
