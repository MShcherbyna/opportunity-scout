<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const contactEmail = 'maxshcherbyna247@gmail.com';
</script>

<template>
    <Head title="Terms of Service" />

    <article class="space-y-6 text-sm leading-relaxed">
        <h1 class="text-2xl font-semibold">Terms of Service</h1>
        <p class="text-muted-foreground">Last updated: October 27, 2026</p>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Agreement to terms</h2>
            <p>
                By creating an account or otherwise using Opportunity Scout
                ("the service"), you agree to these Terms of Service. If you do
                not agree, do not use the service.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">What the service does</h2>
            <p>
                Opportunity Scout helps you find useful public posts on Threads,
                such as job openings and service requests, based on keywords you
                configure, and lets you review them in one place. The service
                connects to your Threads account through the official Threads
                API with your authorization.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Your account</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    You must provide accurate information when creating an
                    account and keep your credentials secure.
                </li>
                <li>
                    You are responsible for all activity that happens through
                    your account, including the keywords you configure and any
                    actions taken with your connected Threads account.
                </li>
                <li>
                    You must be eligible to use Threads and comply with Meta's
                    own Terms of Service and Platform Policies when you connect
                    your Threads account.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Acceptable use</h2>
            <p>You agree not to:</p>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    Use the service to harass, spam, or send unsolicited
                    commercial messages to the authors of collected posts.
                </li>
                <li>
                    Attempt to bypass the rate limits, authentication, or other
                    technical restrictions of the service or the Threads API.
                </li>
                <li>
                    Use the service for any unlawful purpose or in a way that
                    violates Meta's Platform Terms.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Suspension and termination</h2>
            <p>
                We may suspend or terminate your access if you violate these
                terms or misuse the service. You may stop using the service and
                delete your account at any time; see the
                <a href="/data-deletion" class="underline">data deletion</a>
                page for instructions.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Service provided "as is"</h2>
            <p>
                The service is provided on an "as is" and "as available" basis,
                without warranties of any kind. We do not guarantee that Threads
                posts will be found, accurate, or available at any particular
                time, since this depends on the Threads API and the content
                authors post publicly.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Changes to these terms</h2>
            <p>
                We may update these terms from time to time. Continued use of
                the service after a change means you accept the updated terms.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Contact</h2>
            <p>
                Questions about these terms:
                <a :href="`mailto:${contactEmail}`" class="underline">{{
                    contactEmail
                }}</a>
            </p>
        </section>
    </article>
</template>
