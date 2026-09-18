<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AiFilteringSettingsForm from '@/components/settings/AiFilteringSettingsForm.vue';
import ScraperSettingsTabs from '@/components/settings/ScraperSettingsTabs.vue';

defineOptions({
    layout: {
        title: 'Settings',
        description: 'Scraping, AI filtering and integrations',
    },
});
</script>

<template>
    <Head title="AI filtering settings" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ScraperSettingsTabs />
        <AiFilteringSettingsForm />
    </div>
</template>
