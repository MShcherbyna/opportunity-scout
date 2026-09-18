import { computed } from 'vue';
import { mockActivity, mockDashboardStats } from '@/data/dashboard';
import { mockPosts } from '@/data/posts';
import type { ActivityPoint, DashboardStats } from '@/types';

export type UseDashboardStatsReturn = {
    stats: DashboardStats;
    activity: ActivityPoint[];
    latestRelevantCount: number;
};

export function useDashboardStats(): UseDashboardStatsReturn {
    const latestRelevantCount = computed(
        () => mockPosts.filter((post) => post.status === 'relevant').length,
    );

    return {
        stats: mockDashboardStats,
        activity: mockActivity,
        latestRelevantCount: latestRelevantCount.value,
    };
}
