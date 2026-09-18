<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
    change?: string;
}>();
</script>

<template>
    <div class="border-border bg-card rounded-lg border p-4">
        <div class="text-muted-foreground text-xs">{{ label }}</div>
        <div class="mt-1.5 text-2xl font-bold tracking-tight">
            {{ value }}
        </div>
        <div v-if="change" class="text-muted-foreground mt-1 text-xs">
            {{ change }}
        </div>
    </div>
</template>
