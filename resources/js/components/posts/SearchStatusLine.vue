<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime } from '@/lib/threadsCopy';
import type { SearchStatus } from '@/types';

const props = defineProps<{
    searchStatus: SearchStatus;
    isDisconnected: boolean;
}>();

/** Precedence (AC-88): ST-4, ST-6, ST-2, ST-3, ST-5, ST-1. */
const text = computed(() => {
    const status = props.searchStatus;

    if (props.isDisconnected) {
        return 'Stopped: Threads is disconnected.';
    }

    if (status.runState !== 'idle') {
        return 'Searching Threads…';
    }

    if (status.pausedUntil) {
        return `Paused until ${formatDateTime(status.pausedUntil)}: Threads search limit reached.`;
    }

    if (status.lastRunOutcome === 'budget_reached') {
        return 'Daily search budget used up. Search resumes as earlier searches leave the 24-hour window.';
    }

    if (status.lastRunOutcome === 'partial') {
        return 'The last search partly failed. The next search retries.';
    }

    const last = status.lastRunFinishedAt
        ? formatDateTime(status.lastRunFinishedAt)
        : 'not run yet';
    const next = status.nextRunAt ? formatDateTime(status.nextRunAt) : '—';

    return `Last search: ${last} · Next search: ${next}`;
});
</script>

<template>
    <p class="text-muted-foreground text-xs" role="status">{{ text }}</p>
</template>
