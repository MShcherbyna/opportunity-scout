<script setup lang="ts">
import type { ThreadsQuota } from '@/types';

defineProps<{
    quota: ThreadsQuota;
}>();
</script>

<template>
    <div class="border-border bg-card rounded-lg border p-4">
        <h2 class="text-sm font-semibold">Threads searches</h2>
        <p class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
            <span class="font-semibold">
                {{ quota.usedLast24Hours.toLocaleString('en') }} used in the
                last 24 hours
            </span>
            <span class="text-muted-foreground">·</span>
            <span>{{ quota.remaining.toLocaleString('en') }} left</span>
            <span class="text-muted-foreground">·</span>
            <span class="text-muted-foreground">
                Budget {{ quota.dailyBudget.toLocaleString('en') }}
            </span>
        </p>
        <p class="text-muted-foreground mt-2 text-xs">
            Threads allows {{ quota.threadsDailyLimit.toLocaleString('en') }}
            searches per 24 hours for your account, shared with other apps you
            use with Threads. Opportunity Scout plans with
            {{ quota.dailyBudget.toLocaleString('en') }}, so the number left is
            an estimate.
        </p>
    </div>
</template>
