<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ScraperSettingsTabs from '@/components/settings/ScraperSettingsTabs.vue';
import ScrapingSettingsForm from '@/components/settings/ScrapingSettingsForm.vue';

defineOptions({
    layout: {
        title: 'Settings',
        description: 'Scraping, AI filtering and integrations',
    },
});
</script>

<template>
    <Head title="Scraping settings" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ScraperSettingsTabs />
        <ScrapingSettingsForm />
    </div>
</template>
