import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { mockRelevantPosts, mockRelevantSeries } from '@/data/dashboard';
import type { ActivityPoint, FoundActivityPoint } from '@/types';

export type ActivityPeriod = '7' | '30';

export type UseDashboardStatsReturn = {
    period: Ref<ActivityPeriod>;
    activity: ComputedRef<ActivityPoint[]>;
    relevantPosts: { value: number; change: string };
};

function formatDateLabel(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

/** Merges the real found-per-day series with the mock relevant series. */
export function useDashboardStats(
    found: () => FoundActivityPoint[],
): UseDashboardStatsReturn {
    const period = ref<ActivityPeriod>('7');

    const activity = computed<ActivityPoint[]>(() => {
        const days = Number(period.value);
        const slice = found().slice(-days);
        const relevant = mockRelevantSeries(slice.length);

        return slice.map((point, index) => ({
            label: formatDateLabel(point.date),
            found: point.found,
            relevant: relevant[index],
        }));
    });

    return { period, activity, relevantPosts: mockRelevantPosts };
}
