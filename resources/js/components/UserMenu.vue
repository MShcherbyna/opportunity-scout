<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="hover:bg-accent focus-visible:ring-ring/50 flex items-center gap-2 rounded-md p-1.5 pr-2 text-left outline-none focus-visible:ring-2"
            data-test="user-menu-trigger"
        >
            <UserInfo :user="user" :show-email="true" />
            <ChevronDown class="text-muted-foreground size-4 shrink-0" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-64">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
