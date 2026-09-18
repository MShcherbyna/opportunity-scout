import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { generateActivity, mockDashboardStats } from '@/data/dashboard';
import { mockPosts } from '@/data/posts';
import type { ActivityPoint, DashboardStats } from '@/types';

export type ActivityPeriod = '7' | '30';

export type UseDashboardStatsReturn = {
    stats: DashboardStats;
    period: Ref<ActivityPeriod>;
    activity: ComputedRef<ActivityPoint[]>;
    latestRelevantCount: ComputedRef<number>;
};

export function useDashboardStats(): UseDashboardStatsReturn {
    const period = ref<ActivityPeriod>('7');

    const activity = computed(() => generateActivity(Number(period.value)));

    const latestRelevantCount = computed(
        () => mockPosts.filter((post) => post.status === 'relevant').length,
    );

    return {
        stats: mockDashboardStats,
        period,
        activity,
        latestRelevantCount,
    };
}
