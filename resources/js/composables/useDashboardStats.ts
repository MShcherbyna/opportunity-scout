import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { ActivityPoint, FoundActivityPoint } from '@/types';

export type ActivityPeriod = '1' | '7' | '15' | '30';

export type UseDashboardStatsReturn = {
    period: Ref<ActivityPeriod>;
    activity: ComputedRef<ActivityPoint[]>;
};

function formatDateLabel(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Maps the real found-per-day and relevant-per-day series (AC-105).
 */
export function useDashboardStats(
    found: () => FoundActivityPoint[],
): UseDashboardStatsReturn {
    const period = ref<ActivityPeriod>('7');

    const activity = computed<ActivityPoint[]>(() => {
        const days = Number(period.value);
        const slice = found().slice(-days);

        return slice.map((point) => ({
            label: formatDateLabel(point.date),
            found: point.found,
            relevant: point.relevant,
        }));
    });

    return { period, activity };
}
