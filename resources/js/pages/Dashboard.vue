<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, Inbox, Target } from '@lucide/vue';
import ActivityChart from '@/components/ActivityChart.vue';
import MetricCard from '@/components/MetricCard.vue';
import PostsTable from '@/components/posts/PostsTable.vue';
import RunScraperButton from '@/components/RunScraperButton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAiSettings } from '@/composables/useAiSettings';
import type { ActivityPeriod } from '@/composables/useDashboardStats';
import { useDashboardStats } from '@/composables/useDashboardStats';
import { usePosts } from '@/composables/usePosts';
import postsRoutes from '@/routes/posts';
import { aiFiltering } from '@/routes/settings';

defineOptions({
    layout: {
        title: 'Dashboard',
        description: 'Overview of your Threads monitoring activity',
    },
});

const { stats, period, activity } = useDashboardStats();
const { settings: aiSettings } = useAiSettings();

const periodOptions: { value: ActivityPeriod; label: string }[] = [
    { value: '7', label: 'Last 7 days' },
    { value: '30', label: 'Last 30 days' },
];

const { posts, isLoading } = usePosts({
    perPage: 5,
    initialStatusFilter: 'relevant',
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <Alert v-if="!aiSettings.hasApiKey" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertTitle>AI filtering isn't configured yet</AlertTitle>
            <AlertDescription>
                <p>
                    Add a provider and API key so collected posts can be scored
                    automatically.
                </p>
                <Button as-child size="sm" variant="outline" class="mt-2">
                    <Link :href="aiFiltering()">Configure AI filtering</Link>
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
                <RunScraperButton />
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
                :value="stats.postsCollected.value.toLocaleString()"
                :change="stats.postsCollected.change"
                positive
            />
            <MetricCard
                :icon="Target"
                label="Relevant posts"
                :value="stats.relevantPosts.value.toLocaleString()"
                :change="stats.relevantPosts.change"
            />
            <MetricCard
                :icon="CalendarDays"
                label="New today"
                :value="stats.newToday.value.toLocaleString()"
                :change="stats.newToday.change"
            />
        </div>

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
                        Collected posts
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
            <h2 class="text-sm font-semibold">Latest relevant posts</h2>
            <Link
                :href="postsRoutes.index()"
                class="text-primary text-xs font-medium hover:underline"
            >
                View all
            </Link>
        </div>

        <div class="border-border bg-card rounded-lg border">
            <PostsTable
                :posts="posts"
                :is-loading="isLoading"
                variant="dashboard"
            />
        </div>
    </div>
</template>
