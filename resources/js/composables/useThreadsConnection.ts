import type { Ref } from 'vue';
import { ref } from 'vue';
import { mockThreadsAccount } from '@/data/settings';
import type { ThreadsAccount, ThreadsConnectionState } from '@/types';

// Module-level state so the connection persists across navigation within the
// session. Frontend-only for now: connect/disconnect are simulated until the
// Threads OAuth endpoints exist.
const account = ref<ThreadsAccount | null>(null);
const state = ref<ThreadsConnectionState>('idle');

export type UseThreadsConnectionReturn = {
    account: Ref<ThreadsAccount | null>;
    state: Ref<ThreadsConnectionState>;
    connect: () => Promise<void>;
    disconnect: () => Promise<void>;
};

export function useThreadsConnection(): UseThreadsConnectionReturn {
    async function connect() {
        state.value = 'connecting';

        await new Promise((resolve) => setTimeout(resolve, 800));

        account.value = { ...mockThreadsAccount };
        state.value = 'idle';
    }

    async function disconnect() {
        state.value = 'disconnecting';

        await new Promise((resolve) => setTimeout(resolve, 500));

        account.value = null;
        state.value = 'idle';
    }

    return {
        account,
        state,
        connect,
        disconnect,
    };
}
