import type { Ref } from 'vue';
import { ref } from 'vue';

export type UseClipboardReturn = {
    copied: Ref<boolean>;
    copy: (text: string) => Promise<void>;
};

export function useClipboard(resetAfterMs = 2000): UseClipboardReturn {
    const copied = ref(false);
    let resetTimeout: ReturnType<typeof setTimeout> | undefined;

    async function copy(text: string) {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            return;
        }

        copied.value = true;
        clearTimeout(resetTimeout);
        resetTimeout = setTimeout(() => {
            copied.value = false;
        }, resetAfterMs);
    }

    return { copied, copy };
}
