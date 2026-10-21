<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const contactEmail = 'privacy@your-domain.com';
</script>

<template>
    <Head title="Privacy Policy" />

    <article class="space-y-6 text-sm leading-relaxed">
        <h1 class="text-2xl font-semibold">Privacy Policy</h1>
        <p class="text-muted-foreground">Last updated: September 19, 2026</p>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">What Opportunity Scout does</h2>
            <p>
                Opportunity Scout helps you find useful public posts on Threads,
                such as job openings and service requests, based on keywords you
                configure, and lets you review them in one place.
            </p>
            <p>
                The service speeds up response time to job offers and
                opportunities. It helps job seekers and employers connect as
                quickly as possible, so the right people can reach each other
                while a post is still fresh.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Data we collect</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    Account data: your name, email address and hashed password.
                </li>
                <li>
                    Threads data you authorize: your Threads user ID, username
                    and an access token.
                </li>
                <li>
                    Public Threads posts that match your keywords: post ID,
                    text, author username, link and timestamp.
                </li>
                <li>
                    Your settings: keywords, schedule, response template and
                    notification preferences.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">How we use it</h2>
            <p>
                We use this data only to find, score and show relevant posts to
                you, and to send you notifications you enable. We do not sell
                your data and we do not publish anything to Threads on your
                behalf.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Who receives data</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    The AI provider you configure receives the text of collected
                    posts to score their relevance.
                </li>
                <li>
                    Telegram receives notification text if you connect a bot.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Storage and retention</h2>
            <p>
                Access tokens and API keys are stored encrypted. Account data
                and settings are kept until you delete your account.
            </p>
            <p>
                Collected posts are automatically deleted 30 days after they
                were collected, unless you responded to them (copied a suggested
                response or marked the post as relevant). Posts you responded to
                are kept until you delete them or your account.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Your choices</h2>
            <p>
                You can disconnect Threads or delete your account and all
                related data at any time. See the
                <a href="/data-deletion" class="underline">data deletion</a>
                page.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Contact</h2>
            <p>
                Questions about your data:
                <a :href="`mailto:${contactEmail}`" class="underline">{{
                    contactEmail
                }}</a>
            </p>
        </section>
    </article>
</template>
