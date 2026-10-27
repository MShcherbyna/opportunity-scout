import { createInertiaApp, router } from '@inertiajs/vue3';
import type { Page, SharedPageProps } from '@inertiajs/core';
import { applyAppearance, initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import LegalLayout from '@/layouts/LegalLayout.vue';
import AccountLayout from '@/layouts/account/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeTimezoneCookie } from '@/lib/timezoneCookie';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('legal/'):
                return LegalLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('account/'):
                return [AppLayout, AccountLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set up the system theme change listener...
initializeTheme();

// Guests always get the dark theme; authenticated users get their saved
// preference. Re-applied on every Inertia navigation (including the
// initial page load) since the appearance depends on auth state.
router.on('navigate', (event) => {
    const page = (event as CustomEvent<{ page: Page<SharedPageProps> }>).detail
        .page;

    applyAppearance(Boolean(page.props.auth?.user));
});

// This will listen for flash toast data from the server...
initializeFlashToast();

// This will write the browser's IANA time zone to the `timezone` cookie...
initializeTimezoneCookie();
