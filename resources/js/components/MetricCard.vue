<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

withDefaults(
    defineProps<{
        icon: LucideIcon;
        label: string;
        value: string;
        change?: string;
        positive?: boolean;
    }>(),
    {
        positive: false,
    },
);
</script>

<template>
    <div class="border-border bg-card flex gap-3 rounded-lg border p-4">
        <div
            class="bg-primary/10 text-primary flex size-[34px] shrink-0 items-center justify-center rounded-md"
        >
            <component :is="icon" class="size-4" />
        </div>
        <div class="min-w-0">
            <div class="text-muted-foreground text-xs">{{ label }}</div>
            <div class="mt-1 text-xl font-bold tracking-tight">
                {{ value }}
            </div>
            <div
                v-if="change"
                class="mt-1 text-xs"
                :class="
                    positive
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-muted-foreground'
                "
            >
                {{ change }}
            </div>
        </div>
    </div>
</template>
