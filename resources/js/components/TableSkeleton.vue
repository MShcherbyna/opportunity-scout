<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        rows?: number;
        columns?: number;
    }>(),
    {
        rows: 6,
        columns: 4,
    },
);
</script>

<template>
    <div class="divide-border divide-y" role="status" aria-label="Loading">
        <div
            v-for="row in rows"
            :key="row"
            class="flex items-center gap-5 px-3 py-4"
        >
            <Skeleton
                v-for="column in columns"
                :key="column"
                class="h-3 flex-1"
                :class="column === 1 ? 'flex-[2]' : ''"
            />
        </div>
    </div>
</template>
