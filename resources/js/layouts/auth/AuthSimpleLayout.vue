<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LegalLinks from '@/components/LegalLinks.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home, login, register } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
// Inertia keeps this layout mounted across auth/* navigations (it's the
// same resolved layout component for every auth page), so this must be a
// computed — a plain value would only ever reflect the very first page load.
const onSignIn = computed(() => isCurrentUrl(login()));
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col">
        <header class="flex items-center justify-between px-6 py-5 sm:px-10">
            <Link :href="home()" class="flex items-center">
                <AppLogoIcon class="size-8" />
                <span class="sr-only">Opportunity Scout</span>
            </Link>

            <nav class="flex items-center gap-2">
                <Button
                    as-child
                    size="sm"
                    class="rounded-full"
                    :variant="onSignIn ? 'default' : 'outline'"
                >
                    <Link :href="login()">Sign in</Link>
                </Button>
                <Button
                    as-child
                    size="sm"
                    class="rounded-full"
                    :variant="!onSignIn ? 'default' : 'outline'"
                >
                    <Link :href="register()">Sign up</Link>
                </Button>
            </nav>
        </header>

        <div
            class="flex flex-1 flex-col items-center justify-center gap-6 p-6 md:p-10"
        >
            <div class="w-full max-w-sm">
                <div class="flex flex-col gap-8">
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-muted-foreground text-center text-sm">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>

        <footer class="px-6 py-6">
            <LegalLinks />
        </footer>
    </div>
</template>
