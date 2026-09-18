import type { ActivityPoint, DashboardStats } from '@/types';

export const mockDashboardStats: DashboardStats = {
    postsCollected: { value: 12482, change: '+842 this week' },
    relevantPosts: { value: 1936, change: '15.5% relevance' },
    newToday: { value: 128, change: '34 relevant' },
};

export const mockActivity: ActivityPoint[] = [
    { label: 'Mon', collected: 45, relevant: 19 },
    { label: 'Tue', collected: 62, relevant: 26 },
    { label: 'Wed', collected: 55, relevant: 23 },
    { label: 'Thu', collected: 76, relevant: 32 },
    { label: 'Fri', collected: 68, relevant: 29 },
    { label: 'Sat', collected: 88, relevant: 37 },
    { label: 'Sun', collected: 72, relevant: 30 },
];
