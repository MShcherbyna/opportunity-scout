<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { Search } from '@lucide/vue';

withDefaults(
    defineProps<{
        icon?: LucideIcon;
        title: string;
        description?: string;
    }>(),
    {
        icon: () => Search,
    },
);
</script>

<template>
    <div
        class="flex min-h-[420px] flex-col items-center justify-center gap-3 px-6 py-16 text-center"
    >
        <component :is="icon" class="text-muted-foreground/60 size-8" />
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <p v-if="description" class="text-muted-foreground max-w-sm text-sm">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
