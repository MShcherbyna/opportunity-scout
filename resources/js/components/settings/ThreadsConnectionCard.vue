<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { disconnectReasonText, formatDate } from '@/lib/threadsCopy';
import { connect, disconnect } from '@/routes/threads';
import type { ThreadsConnectionDetails } from '@/types';

const props = defineProps<{
    connection: ThreadsConnectionDetails | null;
}>();

const THREE_DAYS_MS = 3 * 24 * 60 * 60 * 1000;

const isConnected = computed(() => props.connection?.status === 'connected');
const isDisconnected = computed(
    () => props.connection?.status === 'disconnected',
);

const isExpiringSoon = computed(() => {
    const expiresAt = props.connection?.tokenExpiresAt;

    return (
        isConnected.value &&
        !!expiresAt &&
        new Date(expiresAt).getTime() - Date.now() <= THREE_DAYS_MS
    );
});

const isRedirecting = ref(false);
const isDisconnecting = ref(false);
const confirmOpen = ref(false);

/** Stays disabled until the browser leaves for Threads; only a failure re-enables it. */
function startConnect() {
    isRedirecting.value = true;

    router.post(
        connect(),
        {},
        {
            onError: () => {
                isRedirecting.value = false;
            },
            onNetworkError: () => {
                isRedirecting.value = false;
            },
        },
    );
}

function confirmDisconnect() {
    router.delete(disconnect(), {
        onStart: () => {
            isDisconnecting.value = true;
        },
        onFinish: () => {
            isDisconnecting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <div class="max-w-xl space-y-4">
        <div class="border-border bg-card rounded-lg border p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold">Threads account</h2>
                    <p class="text-muted-foreground text-xs">
                        Connect your Threads account so Opportunity Scout can
                        search posts on your behalf.
                    </p>
                </div>
                <Badge :variant="isConnected ? 'default' : 'secondary'">
                    {{
                        isConnected
                            ? 'Connected'
                            : isDisconnected
                              ? 'Disconnected'
                              : 'Not connected'
                    }}
                </Badge>
            </div>

            <dl
                v-if="connection && isConnected"
                class="mt-5 grid gap-3 text-sm"
            >
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Account</dt>
                    <dd class="font-medium">@{{ connection.username }}</dd>
                </div>
                <div v-if="connection.name" class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Name</dt>
                    <dd>{{ connection.name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Connected</dt>
                    <dd>{{ formatDate(connection.connectedAt) }}</dd>
                </div>
                <div
                    v-if="connection.tokenExpiresAt"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">Access expires</dt>
                    <dd>{{ formatDate(connection.tokenExpiresAt) }}</dd>
                </div>
            </dl>

            <div v-else-if="isDisconnected" class="mt-5 space-y-1 text-sm">
                <p>Threads is disconnected.</p>
                <p
                    v-if="connection?.disconnectReason"
                    class="text-muted-foreground"
                >
                    {{ disconnectReasonText[connection.disconnectReason] }}
                </p>
            </div>

            <p v-else class="text-muted-foreground mt-5 text-sm">
                You will be redirected to Threads to authorize read access. We
                never post on your behalf.
            </p>

            <div
                v-if="isExpiringSoon && connection?.tokenExpiresAt"
                role="status"
                class="border-border bg-muted/50 mt-5 rounded-md border p-3 text-sm"
            >
                Threads access expires on
                {{ formatDate(connection.tokenExpiresAt) }} and couldn't be
                renewed automatically. Reconnect to keep search running.
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <Button
                    v-if="isConnected"
                    variant="outline"
                    @click="confirmOpen = true"
                >
                    Disconnect
                </Button>
                <Button
                    v-if="!isConnected || isExpiringSoon"
                    :disabled="isRedirecting"
                    @click="startConnect"
                >
                    <Spinner v-if="isRedirecting" />
                    {{
                        isDisconnected || isExpiringSoon
                            ? 'Reconnect Threads'
                            : 'Connect Threads'
                    }}
                </Button>
            </div>
        </div>

        <p
            v-if="isDisconnected && connection?.disconnectReason === 'user'"
            class="text-muted-foreground text-xs"
        >
            To revoke access completely, open Threads → Settings → Account →
            Website permissions and remove Opportunity Scout.
        </p>

        <Dialog v-model:open="confirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disconnect Threads?</DialogTitle>
                    <DialogDescription>
                        Search stops until you reconnect. Your keywords and
                        found posts are kept.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirmOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="isDisconnecting"
                        @click="confirmDisconnect"
                    >
                        <Spinner v-if="isDisconnecting" />
                        Disconnect
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
