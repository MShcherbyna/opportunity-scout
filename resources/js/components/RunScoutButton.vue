<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { manualRunBlockedText } from '@/lib/threadsCopy';
import { store } from '@/routes/searchRuns';
import type { SearchStatus } from '@/types';

const props = defineProps<{
    searchStatus: SearchStatus;
}>();

const isSubmitting = ref(false);

const isRunning = computed(() => props.searchStatus.runState !== 'idle');

const blockedText = computed(() =>
    props.searchStatus.manualRunBlockedReason
        ? manualRunBlockedText(
              props.searchStatus.manualRunBlockedReason,
              props.searchStatus.manualRunAvailableAt,
              props.searchStatus.pausedUntil,
          )
        : '',
);

const isDisabled = computed(
    () =>
        isSubmitting.value ||
        isRunning.value ||
        props.searchStatus.manualRunBlockedReason !== null,
);

function run() {
    router.post(
        store(),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmitting.value = true;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex items-center gap-2">
        <span
            v-if="blockedText"
            class="text-muted-foreground text-xs"
            role="status"
        >
            {{ blockedText }}
        </span>
        <Button size="sm" :disabled="isDisabled" @click="run">
            <RefreshCw
                class="size-3.5"
                :class="{ 'animate-spin': isRunning || isSubmitting }"
            />
            {{ isRunning ? 'Running…' : 'Run scout' }}
        </Button>
    </div>
</template>
