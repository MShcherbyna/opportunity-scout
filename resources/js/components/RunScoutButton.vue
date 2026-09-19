<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useScoutRun } from '@/composables/useScoutRun';

const { isRunning, run } = useScoutRun();
</script>

<template>
    <Button size="sm" :disabled="isRunning" @click="run">
        <RefreshCw class="size-3.5" :class="{ 'animate-spin': isRunning }" />
        {{ isRunning ? 'Running…' : 'Run scout' }}
    </Button>
</template>
