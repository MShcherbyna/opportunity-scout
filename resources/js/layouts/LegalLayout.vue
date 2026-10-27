<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LegalLinks from '@/components/LegalLinks.vue';
import { home } from '@/routes';
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col">
        <header class="px-6 py-5 sm:px-10">
            <Link :href="home()" class="flex items-center gap-2">
                <AppLogoIcon class="size-8" />
                <span class="font-semibold">Opportunity Scout</span>
            </Link>
        </header>

        <main class="mx-auto w-full max-w-2xl flex-1 px-6 py-8">
            <slot />
        </main>

        <footer class="px-6 py-6">
            <LegalLinks />
        </footer>
    </div>
</template>
