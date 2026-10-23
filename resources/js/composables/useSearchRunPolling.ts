import { usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import type { SearchStatus } from '@/types';

const POLL_INTERVAL_MS = 10_000;

/**
 * Polls with a partial reload of `only` every 10 seconds while a search run is
 * queued or running, stops when the run ends and announces the result.
 */
export function useSearchRunPolling(
    searchStatus: () => SearchStatus,
    only: string[],
): void {
    const { start, stop } = usePoll(
        POLL_INTERVAL_MS,
        { only },
        { autoStart: false, mode: 'rest' },
    );

    let wasActive = false;

    watch(
        () => searchStatus().runState !== 'idle',
        (isActive) => {
            if (isActive) {
                wasActive = true;
                start();

                return;
            }

            stop();

            if (wasActive) {
                wasActive = false;
                toast.success(
                    `Search finished: ${searchStatus().lastRunPostsStored ?? 0} new posts.`,
                );
            }
        },
        { immediate: true },
    );
}
