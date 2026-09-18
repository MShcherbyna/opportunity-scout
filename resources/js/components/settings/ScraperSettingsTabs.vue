<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { aiFiltering, integrations, scraping } from '@/routes/settings';
import type { NavItem } from '@/types';

const tabs: NavItem[] = [
    { title: 'Scraping', href: scraping() },
    { title: 'AI Filtering', href: aiFiltering() },
    { title: 'Integrations', href: integrations() },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <nav class="border-border flex gap-1 border-b" aria-label="Settings">
        <Link
            v-for="tab in tabs"
            :key="tab.title"
            :href="tab.href"
            class="-mb-px border-b-2 px-3 py-2.5 text-sm font-medium transition-colors"
            :class="
                isCurrentUrl(tab.href)
                    ? 'border-primary text-foreground'
                    : 'text-muted-foreground hover:text-foreground border-transparent'
            "
            :aria-current="isCurrentUrl(tab.href) ? 'page' : undefined"
        >
            {{ tab.title }}
        </Link>
    </nav>
</template>
