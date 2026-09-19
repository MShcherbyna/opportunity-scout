import type { Ref } from 'vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// Module-level so the running state stays in sync if the button is ever
// rendered in more than one place at once.
const isRunning = ref(false);

export type UseScoutRunReturn = {
    isRunning: Ref<boolean>;
    run: () => Promise<void>;
};

/**
 * Frontend-only mock of a scout run. Does not contact a backend, queue, or
 * scraping worker — it only simulates the loading/success UX.
 */
export function useScoutRun(): UseScoutRunReturn {
    async function run() {
        if (isRunning.value) {
            return;
        }

        isRunning.value = true;

        await new Promise((resolve) => setTimeout(resolve, 1500));

        isRunning.value = false;
        toast.success('Scout run complete — 12 new posts collected');
    }

    return { isRunning, run };
}
