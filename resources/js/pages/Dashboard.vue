<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import ActivityChart from '@/components/ActivityChart.vue';
import MetricCard from '@/components/MetricCard.vue';
import Pagination from '@/components/Pagination.vue';
import PostsTable from '@/components/posts/PostsTable.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAiSettings } from '@/composables/useAiSettings';
import { useDashboardStats } from '@/composables/useDashboardStats';
import { usePosts } from '@/composables/usePosts';
import { useScrapingSettings } from '@/composables/useScrapingSettings';
import { aiFiltering, scraping } from '@/routes/settings';

defineOptions({
    layout: {
        title: 'Dashboard',
        description: 'Threads opportunity monitoring',
    },
});

const { stats, activity } = useDashboardStats();
const { settings: scrapingSettings } = useScrapingSettings();
const { settings: aiSettings } = useAiSettings();

const checkFrequencyLabel: Record<string, string> = {
    '5': 'Every 5 minutes',
    '15': 'Every 15 minutes',
    '30': 'Every 30 minutes',
    '60': 'Every hour',
};

const { posts, isLoading, total, totalPages, page, perPage, setPage } =
    usePosts({ perPage: 3, initialStatusFilter: 'relevant' });
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

        <div class="grid gap-4 md:grid-cols-3">
            <MetricCard
                label="Posts collected"
                :value="stats.postsCollected.value.toLocaleString()"
                :change="stats.postsCollected.change"
            />
            <MetricCard
                label="Relevant posts"
                :value="stats.relevantPosts.value.toLocaleString()"
                :change="stats.relevantPosts.change"
            />
            <MetricCard
                label="New today"
                :value="stats.newToday.value.toLocaleString()"
                :change="stats.newToday.change"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.5fr_.7fr]">
            <div class="border-border bg-card rounded-lg border p-4">
                <h2 class="text-sm font-semibold">Activity · last 7 days</h2>
                <p class="text-muted-foreground text-xs">
                    Collected vs relevant
                </p>
                <ActivityChart :data="activity" class="mt-2" />
            </div>

            <div class="border-border bg-card rounded-lg border p-4">
                <h2 class="text-sm font-semibold">Current search</h2>
                <p class="text-muted-foreground text-xs">
                    {{ checkFrequencyLabel[scrapingSettings.checkFrequency] }}
                </p>
                <div class="mt-3.5 flex flex-wrap gap-1.5">
                    <Badge
                        v-for="keyword in scrapingSettings.keywords"
                        :key="keyword"
                        variant="secondary"
                    >
                        {{ keyword }}
                    </Badge>
                </div>
                <Button as-child variant="outline" size="sm" class="mt-4">
                    <Link :href="scraping()">Edit settings</Link>
                </Button>
            </div>
        </div>

        <div class="border-border bg-card rounded-lg border">
            <div class="p-4">
                <h2 class="text-sm font-semibold">Latest relevant posts</h2>
                <p class="text-muted-foreground text-xs">
                    AI-approved opportunities
                </p>
            </div>

            <PostsTable
                :posts="posts"
                :is-loading="isLoading"
                :show-status="false"
            />

            <div
                v-if="!isLoading && posts.length > 0"
                class="border-border border-t p-4"
            >
                <Pagination
                    :current-page="page"
                    :total-pages="totalPages"
                    :total-items="total"
                    :per-page="perPage"
                    @update:current-page="setPage"
                />
            </div>
        </div>
    </div>
</template>
