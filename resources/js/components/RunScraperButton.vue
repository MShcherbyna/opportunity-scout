<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useScraperRun } from '@/composables/useScraperRun';

const { isRunning, run } = useScraperRun();
</script>

<template>
    <Button size="sm" :disabled="isRunning" @click="run">
        <RefreshCw class="size-3.5" :class="{ 'animate-spin': isRunning }" />
        {{ isRunning ? 'Running…' : 'Run scraper' }}
    </Button>
</template>
