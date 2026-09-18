<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

withDefaults(
    defineProps<{
        score: number;
        /**
         * `accent` — blue outline pill (post detail).
         * `green` — filled green pill (dashboard's relevant-posts widget).
         * `plain` — bare text, no badge (posts table).
         */
        variant?: 'accent' | 'green' | 'plain';
        label?: string;
    }>(),
    {
        variant: 'accent',
    },
);
</script>

<template>
    <span v-if="variant === 'plain'" class="text-sm tabular-nums"
        >{{ score }}%<template v-if="label"> {{ label }}</template></span
    >
    <Badge
        v-else
        variant="outline"
        :class="
            variant === 'green'
                ? 'border-transparent bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                : 'bg-primary/10 text-primary dark:bg-primary/15 border-transparent'
        "
    >
        {{ score }}%<template v-if="label"> {{ label }}</template>
    </Badge>
</template>
