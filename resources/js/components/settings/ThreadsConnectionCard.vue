<script setup lang="ts">
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useThreadsConnection } from '@/composables/useThreadsConnection';

const { account, state, connect, disconnect } = useThreadsConnection();

const isBusy = computed(() => state.value !== 'idle');

const dateFormatter = new Intl.DateTimeFormat('en', { dateStyle: 'medium' });

function formatDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

async function handleConnect() {
    await connect();
    toast.success('Threads account connected');
}

async function handleDisconnect() {
    await disconnect();
    toast.success('Threads account disconnected');
}
</script>

<template>
    <div class="border-border bg-card max-w-xl rounded-lg border p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold">Threads account</h2>
                <p class="text-muted-foreground text-xs">
                    Connect your Threads account so Opportunity Scout can search
                    posts on your behalf.
                </p>
            </div>
            <Badge :variant="account ? 'default' : 'secondary'">
                {{ account ? 'Connected' : 'Not connected' }}
            </Badge>
        </div>

        <dl v-if="account" class="mt-5 grid gap-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Account</dt>
                <dd class="font-medium">@{{ account.username }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Name</dt>
                <dd>{{ account.displayName }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Connected</dt>
                <dd>{{ formatDate(account.connectedAt) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Access expires</dt>
                <dd>{{ formatDate(account.tokenExpiresAt) }}</dd>
            </div>
        </dl>

        <p v-else class="text-muted-foreground mt-5 text-sm">
            You will be redirected to Threads to authorize read access. We never
            post on your behalf.
        </p>

        <div class="mt-5">
            <Button
                v-if="account"
                variant="outline"
                :disabled="isBusy"
                @click="handleDisconnect"
            >
                <Spinner v-if="state === 'disconnecting'" />
                Disconnect
            </Button>
            <Button v-else :disabled="isBusy" @click="handleConnect">
                <Spinner v-if="state === 'connecting'" />
                Connect Threads
            </Button>
        </div>
    </div>
</template>
