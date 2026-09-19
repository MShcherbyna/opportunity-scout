<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ScoutSettingsTabs from '@/components/settings/ScoutSettingsTabs.vue';
import TelegramSettingsForm from '@/components/settings/TelegramSettingsForm.vue';

defineOptions({
    layout: {
        title: 'Settings',
        description: 'Scraping, AI filtering and integrations',
    },
});
</script>

<template>
    <Head title="Integration settings" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ScoutSettingsTabs />
        <TelegramSettingsForm />
    </div>
</template>
