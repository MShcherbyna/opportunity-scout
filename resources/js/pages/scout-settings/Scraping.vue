<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import KeywordsEditor from '@/components/settings/KeywordsEditor.vue';
import ScoutSettingsTabs from '@/components/settings/ScoutSettingsTabs.vue';
import ScrapingSettingsForm from '@/components/settings/ScrapingSettingsForm.vue';
import type { ScrapingKeyword, SearchBudget, SearchSettings } from '@/types';

defineProps<{
    keywords: ScrapingKeyword[];
    searchSettings: SearchSettings;
    searchBudget: SearchBudget;
    ownPostsOnly: boolean;
}>();

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
        <ScoutSettingsTabs />
        <KeywordsEditor
            :keywords="keywords"
            :search-settings="searchSettings"
            :search-budget="searchBudget"
            :own-posts-only="ownPostsOnly"
        />
        <ScrapingSettingsForm
            :key="`${searchSettings.intervalMinutes}-${searchSettings.firstSearchLookbackDays}`"
            :search-settings="searchSettings"
        />
    </div>
</template>
