<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        title?: string;
        description?: string;
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <div v-if="title" class="min-w-0">
                <h1 class="truncate text-sm font-semibold">{{ title }}</h1>
                <p
                    v-if="description"
                    class="text-muted-foreground truncate text-xs"
                >
                    {{ description }}
                </p>
            </div>
            <template v-else-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <UserMenu />
    </header>
</template>
