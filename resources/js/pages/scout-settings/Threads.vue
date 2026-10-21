<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ScoutSettingsTabs from '@/components/settings/ScoutSettingsTabs.vue';
import ThreadsConnectionCard from '@/components/settings/ThreadsConnectionCard.vue';

defineOptions({
    layout: {
        title: 'Settings',
        description: 'Scraping, AI filtering and integrations',
    },
});
</script>

<template>
    <Head title="Threads settings" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ScoutSettingsTabs />
        <ThreadsConnectionCard />
    </div>
</template>
