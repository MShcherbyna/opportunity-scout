<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, Rss, Settings, X } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import LegalLinks from '@/components/LegalLinks.vue';
import NavMain from '@/components/NavMain.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import posts from '@/routes/posts';
import { keywords } from '@/routes/settings';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Posts',
        href: posts.index(),
        icon: Rss,
    },
    {
        title: 'Settings',
        href: keywords(),
        icon: Settings,
        activeMatch: '/settings',
    },
];

const { toggleSidebar } = useSidebar();
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="inset">
        <SidebarHeader>
            <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                <Link
                    :href="dashboard()"
                    class="flex min-w-0 flex-1 items-center gap-2"
                >
                    <AppLogo />
                </Link>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-7 shrink-0"
                    title="Close menu"
                    @click="toggleSidebar"
                >
                    <X class="size-4" />
                    <span class="sr-only">Close menu</span>
                </Button>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <LegalLinks />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
