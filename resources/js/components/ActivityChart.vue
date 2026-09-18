<script setup lang="ts">
import { computed } from 'vue';
import type { ActivityPoint } from '@/types';

const props = defineProps<{
    data: ActivityPoint[];
}>();

const max = computed(() =>
    Math.max(
        1,
        ...props.data.flatMap((point) => [point.collected, point.relevant]),
    ),
);
</script>

<template>
    <div>
        <div
            class="border-border flex h-[150px] items-end gap-2 border-b pt-4"
            role="img"
            :aria-label="`Weekly activity: ${data.map((point) => `${point.label} — ${point.collected} collected, ${point.relevant} relevant`).join('; ')}`"
        >
            <div
                v-for="point in data"
                :key="point.label"
                class="flex h-full flex-1 items-end gap-[3px]"
                aria-hidden="true"
            >
                <div
                    class="bg-muted-foreground/40 w-1/2 rounded-t-sm"
                    :style="{ height: `${(point.collected / max) * 100}%` }"
                />
                <div
                    class="bg-primary/80 w-1/2 rounded-t-sm"
                    :style="{ height: `${(point.relevant / max) * 100}%` }"
                />
            </div>
        </div>
        <div
            class="text-muted-foreground mt-2 flex justify-between text-[10px]"
        >
            <span v-for="point in data" :key="point.label">{{
                point.label
            }}</span>
        </div>
        <div class="mt-3 flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1.5">
                <span class="bg-muted-foreground/40 size-2 rounded-sm" />
                Collected
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-primary/80 size-2 rounded-sm" />
                Relevant
            </span>
        </div>
    </div>
</template>
