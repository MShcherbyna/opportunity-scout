<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, Inbox, Target } from '@lucide/vue';
import ActivityChart from '@/components/ActivityChart.vue';
import MetricCard from '@/components/MetricCard.vue';
import PostsTable from '@/components/posts/PostsTable.vue';
import RunScoutButton from '@/components/RunScoutButton.vue';
import ThreadsQuotaCard from '@/components/ThreadsQuotaCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ActivityPeriod } from '@/composables/useDashboardStats';
import { useDashboardStats } from '@/composables/useDashboardStats';
import { useSearchRunPolling } from '@/composables/useSearchRunPolling';
import postsRoutes from '@/routes/posts';
import { scraping, threads } from '@/routes/settings';
import type {
    DashboardThreadsStats,
    FoundActivityPoint,
    SearchStatus,
    ThreadsPost,
    ThreadsQuota,
} from '@/types';

const props = defineProps<{
    threadsQuota: ThreadsQuota;
    stats: DashboardThreadsStats;
    activity: FoundActivityPoint[];
    latestPosts: ThreadsPost[];
    searchStatus: SearchStatus;
}>();

defineOptions({
    layout: {
        title: 'Dashboard',
        description: 'Overview of your Threads monitoring activity',
    },
});

const { period, activity } = useDashboardStats(() => props.activity);

const periodOptions: { value: ActivityPeriod; label: string }[] = [
    { value: '1', label: 'Last 1 day' },
    { value: '7', label: 'Last 7 days' },
    { value: '15', label: 'Last 15 days' },
    { value: '30', label: 'Last 30 days' },
];

useSearchRunPolling(() => props.searchStatus, ['searchStatus', 'latestPosts']);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <Alert
            v-if="searchStatus.manualRunBlockedReason === 'not_connected'"
            variant="destructive"
        >
            <AlertTriangle class="size-4" />
            <AlertTitle>Threads isn't connected yet</AlertTitle>
            <AlertDescription>
                <p>
                    Connect your Threads account so Opportunity Scout can search
                    it for your keywords.
                </p>
                <Button as-child size="sm" variant="outline" class="mt-2">
                    <Link :href="threads()">Connect Threads</Link>
                </Button>
            </AlertDescription>
        </Alert>

        <Alert
            v-else-if="searchStatus.manualRunBlockedReason === 'no_keywords'"
            variant="destructive"
        >
            <AlertTriangle class="size-4" />
            <AlertTitle>No keywords yet</AlertTitle>
            <AlertDescription>
                <p>Add a keyword so Opportunity Scout can search Threads.</p>
                <Button as-child size="sm" variant="outline" class="mt-2">
                    <Link :href="scraping()">Add keywords</Link>
                </Button>
            </AlertDescription>
        </Alert>

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-muted-foreground mt-0.5 text-xs">
                    Overview of your Threads monitoring activity
                </p>
            </div>
            <div class="flex items-center gap-2">
                <RunScoutButton :search-status="searchStatus" />
                <Select
                    :model-value="period"
                    @update:model-value="period = $event as ActivityPeriod"
                >
                    <SelectTrigger class="w-full sm:w-36">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in periodOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <MetricCard
                :icon="Inbox"
                label="Posts collected"
                :value="stats.postsFound.toLocaleString()"
                :change="`+${stats.foundLast7Days.toLocaleString()} in the last 7 days`"
                positive
            />
            <MetricCard
                :icon="Target"
                label="Relevant posts"
                :value="stats.relevantPosts.toLocaleString()"
            />
            <MetricCard
                :icon="CalendarDays"
                label="New today"
                :value="stats.foundToday.toLocaleString()"
            />
        </div>

        <ThreadsQuotaCard :quota="threadsQuota" />

        <div class="border-border bg-card rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Activity overview</h2>
                <div
                    class="text-muted-foreground flex items-center gap-4 text-xs"
                >
                    <span class="flex items-center gap-1.5">
                        <span
                            class="bg-muted-foreground/40 size-2 rounded-sm"
                        />
                        Found posts
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="bg-primary/80 size-2 rounded-sm" />
                        Relevant posts
                    </span>
                </div>
            </div>
            <ActivityChart :data="activity" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold">Latest posts</h2>
            <Link
                :href="postsRoutes.index()"
                class="text-primary text-xs font-medium hover:underline"
            >
                View all
            </Link>
        </div>

        <div class="border-border bg-card rounded-lg border">
            <PostsTable :posts="latestPosts" variant="dashboard" />
        </div>
    </div>
</template>
