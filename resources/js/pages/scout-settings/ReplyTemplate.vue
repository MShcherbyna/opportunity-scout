<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReplyTemplateForm from '@/components/settings/ReplyTemplateForm.vue';
import ScoutSettingsTabs from '@/components/settings/ScoutSettingsTabs.vue';

defineProps<{
    replyTemplate: string | null;
}>();

defineOptions({
    layout: {
        title: 'Settings',
        description: 'Keywords and Threads connection',
    },
});
</script>

<template>
    <Head title="Reply template settings" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ScoutSettingsTabs />
        <ReplyTemplateForm
            :key="replyTemplate ?? ''"
            :reply-template="replyTemplate"
        />
    </div>
</template>
