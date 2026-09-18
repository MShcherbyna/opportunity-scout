<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const {
    breadcrumbs = [],
    title,
    description,
} = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :title="title"
        :description="description"
    >
        <slot />
    </AppLayout>
</template>
