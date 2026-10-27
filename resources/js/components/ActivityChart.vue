<script setup lang="ts">
import { computed } from 'vue';
import type { ActivityPoint } from '@/types';

const props = defineProps<{
    data: ActivityPoint[];
}>();

const max = computed(() =>
    Math.max(
        1,
        ...props.data.flatMap((point) => [point.found, point.relevant]),
    ),
);

// Thin out date labels once there are too many bars (e.g. a 30-day view) to
// keep them legible.
const labelStep = computed(() => Math.ceil(props.data.length / 10));
</script>

<template>
    <div>
        <div
            class="border-border flex h-[190px] items-end gap-2 border-b pt-4"
            role="img"
            :aria-label="`Activity: ${data.map((point) => `${point.label} — ${point.found} found, ${point.relevant} relevant`).join('; ')}`"
        >
            <div
                v-for="point in data"
                :key="point.label"
                class="flex h-full flex-1 items-end gap-[3px]"
                aria-hidden="true"
            >
                <div
                    class="bg-muted-foreground/40 w-1/2 rounded-t-sm"
                    :style="{ height: `${(point.found / max) * 100}%` }"
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
            <span
                v-for="(point, index) in data"
                :key="point.label"
                class="flex-1 text-center"
                >{{ index % labelStep === 0 ? point.label : '' }}</span
            >
        </div>
    </div>
</template>
