<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const contactEmail = 'support@oppscout.app';
</script>

<template>
    <Head title="Privacy Policy" />

    <article class="space-y-6 text-sm leading-relaxed">
        <h1 class="text-2xl font-semibold">Privacy Policy</h1>
        <p class="text-muted-foreground">Last updated: October 27, 2026</p>

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
            <h2 class="text-lg font-medium">
                Legal basis for processing (GDPR)
            </h2>
            <p>
                If you are located in the European Economic Area or the United
                Kingdom, we process your personal data on these legal bases
                under the General Data Protection Regulation (GDPR):
            </p>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    <strong>Contract:</strong> to create your account, connect
                    your Threads account and provide the service you signed up
                    for.
                </li>
                <li>
                    <strong>Consent:</strong> for optional integrations you
                    enable, such as sending post text to an AI provider. You can
                    withdraw this consent at any time by disabling the
                    integration.
                </li>
                <li>
                    <strong>Legitimate interest:</strong> to keep the service
                    secure, prevent abuse, and improve reliability.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">Who receives data</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    The AI provider you configure receives the text of collected
                    posts to score their relevance.
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-medium">International data transfers</h2>
            <p>
                Where an AI provider processes data outside the European
                Economic Area or the United Kingdom, we rely on that provider's
                standard contractual clauses or an equivalent safeguard
                recognized under GDPR for the transfer.
            </p>
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
            <h2 class="text-lg font-medium">Your rights under GDPR</h2>
            <p>
                If you are located in the European Economic Area or the United
                Kingdom, you have the right to:
            </p>
            <ul class="list-disc space-y-1 pl-5">
                <li>Access the personal data we hold about you.</li>
                <li>Request correction of inaccurate data.</li>
                <li>
                    Request erasure of your data (see
                    <a href="/data-deletion" class="underline">data deletion</a
                    >).
                </li>
                <li>Request restriction of processing in certain cases.</li>
                <li>
                    Receive your data in a portable, machine-readable format.
                </li>
                <li>
                    Object to processing based on legitimate interest, and
                    withdraw consent for optional integrations at any time.
                </li>
                <li>
                    Lodge a complaint with your local data protection
                    supervisory authority.
                </li>
            </ul>
            <p>
                To exercise any of these rights, contact us using the details
                below.
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
