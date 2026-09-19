<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const contactEmail = 'privacy@your-domain.com';
</script>

<template>
    <Head title="Data deletion" />

    <article class="space-y-6 text-sm leading-relaxed">
        <h1 class="text-2xl font-semibold">Data deletion</h1>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Delete your account</h2>
            <ol class="list-decimal space-y-1 pl-5">
                <li>Sign in to Opportunity Scout.</li>
                <li>Open Settings → Profile.</li>
                <li>Choose “Delete account” and confirm with your password.</li>
            </ol>
            <p>
                This permanently removes your account, Threads connection,
                collected posts and settings.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Revoke Threads access</h2>
            <p>
                You can also remove Opportunity Scout in Threads under Settings →
                Account → Website permissions.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Can't sign in?</h2>
            <p>
                Email
                <a :href="`mailto:${contactEmail}`" class="underline">{{
                    contactEmail
                }}</a>
                from your account address and we will delete your data.
            </p>
        </section>
    </article>
</template>
