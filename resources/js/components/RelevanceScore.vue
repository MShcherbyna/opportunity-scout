<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = withDefaults(
    defineProps<{
        score: number;
        threshold?: number;
    }>(),
    {
        threshold: 75,
    },
);

const isHigh = computed(() => props.score >= props.threshold);
</script>

<template>
    <Badge
        variant="outline"
        :class="
            isHigh
                ? 'bg-primary/10 text-primary dark:bg-primary/15 border-transparent'
                : 'bg-muted text-muted-foreground border-transparent'
        "
    >
        {{ score }}%
    </Badge>
</template>
