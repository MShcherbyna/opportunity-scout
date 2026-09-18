import type { ActivityPoint, DashboardStats } from '@/types';

export const mockDashboardStats: DashboardStats = {
    postsCollected: { value: 12482, change: '+842 this week' },
    relevantPosts: { value: 1936, change: '15.5% relevance' },
    newToday: { value: 128, change: '34 relevant' },
};

const DAY_PATTERN = [58, 64, 73, 69, 74, 68, 72, 61, 70, 66, 75, 63, 71, 67];

function formatDateLabel(date: Date): string {
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

/** Deterministic activity series for the last N days, ending today. */
export function generateActivity(days: number): ActivityPoint[] {
    const today = new Date();

    return Array.from({ length: days }, (_, index) => {
        const dayOffset = days - 1 - index;
        const date = new Date(today);
        date.setDate(date.getDate() - dayOffset);

        const collected = 40 + DAY_PATTERN[index % DAY_PATTERN.length];
        const relevant = Math.round(collected * 0.47);

        return {
            label: formatDateLabel(date),
            collected,
            relevant,
        };
    });
}
