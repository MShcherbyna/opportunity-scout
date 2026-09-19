<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AiFilteringSettingsForm from '@/components/settings/AiFilteringSettingsForm.vue';
import ScoutSettingsTabs from '@/components/settings/ScoutSettingsTabs.vue';

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
        <ScoutSettingsTabs />
        <AiFilteringSettingsForm />
    </div>
</template>
