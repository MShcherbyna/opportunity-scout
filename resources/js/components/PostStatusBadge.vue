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
                class: 'text-primary',
            };
        case 'maybe':
            return {
                label: 'Maybe',
                icon: CircleHelp,
                class: 'text-primary',
            };
        case 'rejected':
        default:
            return {
                label: 'Rejected',
                icon: XCircle,
                class: 'text-muted-foreground',
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
