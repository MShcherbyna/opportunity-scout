<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', icon: Sun, label: 'Light theme' },
    { value: 'dark', icon: Moon, label: 'Dark theme' },
    { value: 'system', icon: Monitor, label: 'System theme' },
] as const;
</script>

<template>
    <div
        class="bg-muted inline-flex items-center gap-0.5 rounded-md p-0.5"
        role="radiogroup"
        aria-label="Theme"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="appearance === option.value"
            :title="option.label"
            :class="
                cn(
                    'inline-flex size-7 items-center justify-center rounded-sm transition-colors',
                    appearance === option.value
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
            @click="updateAppearance(option.value)"
        >
            <component :is="option.icon" class="size-3.5" />
            <span class="sr-only">{{ option.label }}</span>
        </button>
    </div>
</template>
