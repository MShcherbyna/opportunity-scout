<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { disconnectReasonText } from '@/lib/threadsCopy';
import { threads } from '@/routes/settings';

const page = usePage();

const connection = computed(() => page.props.threadsConnection ?? null);

const isDisconnected = computed(
    () => connection.value?.status === 'disconnected',
);

const reasonText = computed(() =>
    connection.value?.disconnectReason
        ? disconnectReasonText[connection.value.disconnectReason]
        : '',
);
</script>

<template>
    <div
        v-if="isDisconnected"
        role="alert"
        class="border-destructive/30 bg-destructive/10 text-destructive flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-4 py-2 text-sm"
    >
        <AlertTriangle class="size-4 shrink-0" />
        <span>
            Threads is disconnected. Search is stopped. {{ reasonText }}
        </span>
        <Link :href="threads()" class="font-medium underline">
            Reconnect Threads
        </Link>
    </div>
</template>
