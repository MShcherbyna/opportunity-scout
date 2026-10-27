<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Bell,
    Clock,
    FileText,
    Filter,
    Lock,
    Sparkles,
    Target,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dataDeletion, login, privacy, register, terms } from '@/routes';

const features = [
    {
        icon: Target,
        title: 'Keyword configuration',
        description:
            'Track any number of keywords and phrases relevant to your niche — job titles, service requests, product mentions.',
    },
    {
        icon: Clock,
        title: 'Configurable fetch frequency',
        description:
            'Choose how often each search runs, from every few minutes to a daily sweep — scheduled and dispatched automatically.',
    },
    {
        icon: Filter,
        title: 'Smart filtering',
        description:
            'Posts are fetched through the official Threads API, filtered against your rules, and only the relevant ones are stored.',
    },
    {
        icon: BarChart3,
        title: 'Dashboard & statistics',
        description:
            'A single overview of how many posts were found, which keywords perform best, and how activity trends over time.',
    },
    {
        icon: FileText,
        title: 'Saved response templates',
        description:
            'Keep a predefined reply ready to go, so you can respond to a fresh opportunity before anyone else does.',
    },
    {
        icon: Bell,
        title: 'Instant notifications',
        description:
            "Get notified the moment a new relevant post is found, so time-sensitive opportunities don't slip by.",
    },
];

const steps = [
    {
        number: '01',
        title: 'Connect your Threads account',
        description:
            'Sign in and authorize Opportunity Scout against the official Threads Graph API — no credentials are shared with anyone else.',
    },
    {
        number: '02',
        title: 'Set keywords & schedule',
        description:
            'Add the keywords that define an opportunity for you and pick how often each one should be checked.',
    },
    {
        number: '03',
        title: 'Review matches & respond',
        description:
            'New matches land on your dashboard with a link straight back to Threads, plus your saved reply ready to send.',
    },
];

const keywordBreakdown = [
    { label: '"react developer"', value: 46 },
    { label: '"freelance laravel"', value: 31 },
    { label: '"ui design help"', value: 23 },
];

const weeklyActivity = [
    { day: 'Mon', value: 35, highlight: false },
    { day: 'Tue', value: 52, highlight: false },
    { day: 'Wed', value: 44, highlight: false },
    { day: 'Thu', value: 78, highlight: true },
    { day: 'Fri', value: 60, highlight: false },
    { day: 'Sat', value: 28, highlight: false },
    { day: 'Sun', value: 90, highlight: true },
];
</script>

<template>
    <Head title="Opportunity Scout — Find opportunities on Threads" />

    <div
        class="flex min-h-screen w-full flex-col bg-[#0d1117] font-sans text-[#e6edf3] selection:bg-[#3b82f6] selection:text-[#0d1117]"
    >
        <!-- Header -->
        <header
            class="sticky top-0 z-20 border-b border-[#30363d] bg-[#0d1117]/92 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-[72px] max-w-6xl flex-wrap items-center justify-between gap-4 px-6"
            >
                <a href="#top" class="flex shrink-0 items-center gap-2.5">
                    <img
                        src="/images/opportunity-scout-icon.png"
                        alt="Opportunity Scout"
                        class="size-8 rounded-lg"
                    />
                    <span class="text-[17px] font-semibold tracking-tight">
                        Opportunity Scout
                    </span>
                </a>

                <nav aria-label="Primary" class="flex items-center gap-7">
                    <a
                        href="#features"
                        class="text-sm font-medium text-[#8b949e] transition-colors hover:text-[#e6edf3]"
                    >
                        Features
                    </a>
                    <a
                        href="#how-it-works"
                        class="text-sm font-medium text-[#8b949e] transition-colors hover:text-[#e6edf3]"
                    >
                        How it works
                    </a>
                    <a
                        href="#insights"
                        class="text-sm font-medium text-[#8b949e] transition-colors hover:text-[#e6edf3]"
                    >
                        Dashboard
                    </a>
                </nav>

                <div class="flex shrink-0 items-center gap-4">
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-[#e6edf3] hover:text-[#58a6ff]"
                    >
                        Sign in
                    </Link>
                    <Button
                        as-child
                        class="bg-[#3b82f6] text-[#0d1117] hover:bg-[#5b9bff]"
                    >
                        <Link :href="register()">
                            Get Started
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>
        </header>

        <main id="top" class="w-full">
            <!-- Hero -->
            <section class="px-6 pt-24 pb-22">
                <div
                    class="mx-auto flex max-w-6xl flex-wrap items-center gap-16"
                >
                    <div
                        class="flex min-w-0 flex-1 basis-[480px] flex-col gap-7"
                    >
                        <Badge
                            class="w-fit gap-2 rounded-full border border-[#3b82f6]/30 bg-[#3b82f6]/12 px-3 py-1.5 text-[13px] font-semibold text-[#3b82f6] hover:bg-[#3b82f6]/12"
                        >
                            <Sparkles class="size-3.5" />
                            Automated Threads monitoring
                        </Badge>

                        <h1
                            class="text-[52px] leading-[1.08] font-bold tracking-tight"
                        >
                            Find the opportunities<br />
                            hiding inside Threads.
                        </h1>

                        <p
                            class="max-w-[46ch] text-lg leading-relaxed text-[#8b949e]"
                        >
                            Opportunity Scout watches Threads for the keywords
                            you care about — job openings, freelance gigs,
                            service requests — and brings the relevant posts to
                            one dashboard so you never have to scroll the feed
                            again.
                        </p>

                        <div class="flex flex-wrap items-center gap-5">
                            <Button
                                as-child
                                size="lg"
                                class="h-auto gap-2.5 rounded-[10px] bg-[#3b82f6] px-6 py-3.5 text-base font-semibold text-[#0d1117] hover:bg-[#5b9bff]"
                            >
                                <Link :href="register()">
                                    Get Started
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <a
                                href="#how-it-works"
                                class="inline-flex items-center gap-2 border-b border-transparent py-3.5 text-[15px] font-medium text-[#e6edf3] hover:border-[#58a6ff] hover:text-[#58a6ff]"
                            >
                                See how it works
                            </a>
                        </div>

                        <div
                            class="flex items-center gap-2.5 pt-2 text-[13px] text-[#656d76]"
                        >
                            <Lock class="size-3.5" />
                            Connects to your own Threads account · keywords and
                            schedule stay fully in your control
                        </div>
                    </div>

                    <!-- Dashboard mockup -->
                    <div class="min-w-0 flex-1 basis-[460px]">
                        <div
                            class="overflow-hidden rounded-2xl border border-[#30363d] bg-[#161b22] shadow-[0_24px_60px_rgba(0,0,0,0.45)]"
                        >
                            <div
                                class="flex items-center gap-2 border-b border-[#30363d] px-[18px] py-3.5"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-[#30363d]"
                                />
                                <span
                                    class="size-2.5 rounded-full bg-[#30363d]"
                                />
                                <span
                                    class="size-2.5 rounded-full bg-[#30363d]"
                                />
                                <span class="ml-2 text-xs text-[#656d76]"
                                    >Dashboard</span
                                >
                            </div>

                            <div class="flex flex-col gap-5 p-6">
                                <div class="grid grid-cols-3 gap-3">
                                    <div
                                        class="rounded-[10px] border border-[#30363d] bg-[#0d1117] p-3.5"
                                    >
                                        <div class="text-xs text-[#8b949e]">
                                            Posts found
                                        </div>
                                        <div class="mt-1 text-[22px] font-bold">
                                            142
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-[10px] border border-[#30363d] bg-[#0d1117] p-3.5"
                                    >
                                        <div class="text-xs text-[#8b949e]">
                                            Active keywords
                                        </div>
                                        <div class="mt-1 text-[22px] font-bold">
                                            8
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-[10px] border border-[#30363d] bg-[#0d1117] p-3.5"
                                    >
                                        <div class="text-xs text-[#8b949e]">
                                            New today
                                        </div>
                                        <div
                                            class="mt-1 text-[22px] font-bold text-[#3b82f6]"
                                        >
                                            6
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-col gap-2.5 rounded-[10px] border border-[#30363d] bg-[#0d1117] p-4"
                                >
                                    <div class="text-xs text-[#8b949e]">
                                        Posts found per day
                                    </div>
                                    <div class="flex h-18 items-end gap-1.5">
                                        <span
                                            v-for="(bar, index) in [
                                                30, 48, 38, 68, 55, 42, 80,
                                            ]"
                                            :key="index"
                                            class="flex-1 rounded-t-[3px]"
                                            :class="
                                                index === 3 || index === 6
                                                    ? 'bg-[#3b82f6]'
                                                    : 'bg-[#30363d]'
                                            "
                                            :style="{ height: `${bar}%` }"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2.5">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="text-xs text-[#8b949e]"
                                            >Recent matches</span
                                        >
                                        <span
                                            class="rounded-full border border-[#30363d] px-2 py-0.5 text-[11px] text-[#656d76]"
                                        >
                                            keyword: "react dev"
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-start gap-2.5 rounded-[10px] border border-[#30363d] bg-[#0d1117] p-2.5"
                                    >
                                        <span
                                            aria-hidden="true"
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#30363d] text-[11px] font-semibold text-[#8b949e]"
                                        >
                                            JM
                                        </span>
                                        <div
                                            class="flex min-w-0 flex-col gap-1"
                                        >
                                            <p
                                                class="text-[13px] leading-snug text-[#e6edf3]"
                                            >
                                                "Looking for a React developer
                                                for a 2-week sprint, remote,
                                                paid..."
                                            </p>
                                            <a
                                                href="#"
                                                class="text-xs text-[#3b82f6]"
                                            >
                                                View on Threads →
                                            </a>
                                        </div>
                                    </div>

                                    <div
                                        class="flex items-start gap-2.5 rounded-[10px] border border-[#30363d] bg-[#0d1117] p-2.5"
                                    >
                                        <span
                                            aria-hidden="true"
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#30363d] text-[11px] font-semibold text-[#8b949e]"
                                        >
                                            AK
                                        </span>
                                        <div
                                            class="flex min-w-0 flex-col gap-1"
                                        >
                                            <p
                                                class="text-[13px] leading-snug text-[#e6edf3]"
                                            >
                                                "Need someone to help with a
                                                small Laravel API, few days of
                                                work..."
                                            </p>
                                            <a
                                                href="#"
                                                class="text-xs text-[#3b82f6]"
                                            >
                                                View on Threads →
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Trust strip -->
            <section class="border-y border-[#30363d] bg-[#0b0e13]">
                <div
                    class="mx-auto flex max-w-6xl justify-center px-6 py-6 text-center"
                >
                    <span class="text-[13px] text-[#656d76]">
                        Self-hosted · runs on your own Threads Graph API
                        credentials
                    </span>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="px-6 py-24">
                <div class="mx-auto flex max-w-6xl flex-col gap-12">
                    <div class="flex max-w-[640px] flex-col gap-4">
                        <span
                            class="text-[13px] font-semibold tracking-wide text-[#3b82f6] uppercase"
                        >
                            Features
                        </span>
                        <h2 class="text-4xl font-bold tracking-tight">
                            Everything you need to turn Threads into a lead
                            source.
                        </h2>
                        <p class="text-base leading-relaxed text-[#8b949e]">
                            From keyword setup to the final reply, Opportunity
                            Scout handles the repetitive scanning so you can
                            focus on the posts that actually matter.
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-5"
                    >
                        <div
                            v-for="feature in features"
                            :key="feature.title"
                            class="flex flex-col gap-3.5 rounded-2xl border border-[#30363d] bg-[#161b22] p-6 transition-colors hover:border-[#3b82f6]"
                        >
                            <span
                                class="flex size-10 items-center justify-center rounded-[10px] bg-[#3b82f6]/12 text-[#3b82f6]"
                            >
                                <component :is="feature.icon" class="size-5" />
                            </span>
                            <h3 class="text-[17px] font-semibold">
                                {{ feature.title }}
                            </h3>
                            <p class="text-sm leading-relaxed text-[#8b949e]">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section
                id="how-it-works"
                class="border-y border-[#30363d] bg-[#0b0e13] px-6 py-24"
            >
                <div class="mx-auto flex max-w-6xl flex-col gap-12">
                    <div class="flex max-w-[640px] flex-col gap-4">
                        <span
                            class="text-[13px] font-semibold tracking-wide text-[#3b82f6] uppercase"
                        >
                            How it works
                        </span>
                        <h2 class="text-4xl font-bold tracking-tight">
                            From empty inbox to filtered opportunities in three
                            steps.
                        </h2>
                    </div>

                    <div
                        class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-6"
                    >
                        <div
                            v-for="step in steps"
                            :key="step.number"
                            class="flex flex-col gap-4 rounded-2xl border border-[#30363d] bg-[#161b22] p-7 transition-colors hover:border-[#3b82f6]"
                        >
                            <span class="text-sm font-bold text-[#3b82f6]">
                                {{ step.number }}
                            </span>
                            <h3 class="text-lg font-semibold">
                                {{ step.title }}
                            </h3>
                            <p class="text-sm leading-relaxed text-[#8b949e]">
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Insights -->
            <section id="insights" class="px-6 py-24">
                <div
                    class="mx-auto flex max-w-6xl flex-wrap items-center gap-14"
                >
                    <div class="min-w-0 flex-1 basis-[420px] flex-col gap-4">
                        <span
                            class="text-[13px] font-semibold tracking-wide text-[#3b82f6] uppercase"
                        >
                            Insights
                        </span>
                        <h2 class="mt-4 text-4xl font-bold tracking-tight">
                            Know which keywords are actually working.
                        </h2>
                        <p
                            class="mt-4 max-w-[46ch] text-base leading-relaxed text-[#8b949e]"
                        >
                            The dashboard breaks down discovered posts by
                            keyword and by day, so you can tell which searches
                            are worth keeping — and prune the noise.
                        </p>

                        <div class="mt-3 flex flex-col gap-3.5">
                            <div
                                v-for="keyword in keywordBreakdown"
                                :key="keyword.label"
                            >
                                <div
                                    class="mb-1.5 flex justify-between text-[13px] text-[#8b949e]"
                                >
                                    <span>{{ keyword.label }}</span>
                                    <span>{{ keyword.value }}%</span>
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-[#30363d]"
                                >
                                    <span
                                        class="block h-full rounded-full bg-[#3b82f6]"
                                        :style="{ width: `${keyword.value}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 basis-[420px]">
                        <div
                            class="flex flex-col gap-5.5 rounded-2xl border border-[#30363d] bg-[#161b22] p-7"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold">
                                    Posts discovered — last 7 days
                                </span>
                                <span class="text-xs text-[#656d76]"
                                    >weekly</span
                                >
                            </div>

                            <div class="flex h-40 items-end gap-3.5">
                                <div
                                    v-for="day in weeklyActivity"
                                    :key="day.day"
                                    class="flex h-full flex-1 flex-col items-center justify-end gap-2"
                                >
                                    <span
                                        class="w-full rounded-t"
                                        :class="
                                            day.highlight
                                                ? 'bg-[#3b82f6]'
                                                : 'bg-[#30363d]'
                                        "
                                        :style="{ height: `${day.value}%` }"
                                    />
                                    <span
                                        class="text-[11px]"
                                        :class="
                                            day.highlight
                                                ? 'text-[#e6edf3]'
                                                : 'text-[#656d76]'
                                        "
                                    >
                                        {{ day.day }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="flex gap-5 border-t border-[#30363d] pt-4"
                            >
                                <div>
                                    <div class="text-xs text-[#8b949e]">
                                        Total found
                                    </div>
                                    <div class="mt-0.5 text-xl font-bold">
                                        387
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-[#8b949e]">
                                        Avg. per day
                                    </div>
                                    <div class="mt-0.5 text-xl font-bold">
                                        55
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-[#8b949e]">
                                        Response rate
                                    </div>
                                    <div
                                        class="mt-0.5 text-xl font-bold text-[#3b82f6]"
                                    >
                                        62%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="border-t border-[#30363d] px-6 py-24">
                <div
                    class="mx-auto flex max-w-[760px] flex-col items-center gap-6 rounded-[20px] border border-[#30363d] bg-[#161b22] px-10 py-16 text-center"
                >
                    <img
                        src="/images/opportunity-scout-icon.png"
                        alt="Opportunity Scout"
                        class="size-12 rounded-xl"
                    />
                    <h2
                        class="text-[32px] leading-tight font-bold tracking-tight"
                    >
                        Stop scrolling Threads for opportunities.<br />
                        Let Opportunity Scout do it.
                    </h2>
                    <p class="max-w-[46ch] text-base text-[#8b949e]">
                        Create an account, connect your Threads profile, and set
                        your first keyword in a couple of minutes.
                    </p>
                    <Button
                        as-child
                        size="lg"
                        class="h-auto gap-2.5 rounded-[10px] bg-[#3b82f6] px-7 py-3.5 text-base font-semibold text-[#0d1117] hover:bg-[#5b9bff]"
                    >
                        <Link :href="register()">
                            Get Started
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-[#30363d] px-6 pt-14 pb-8">
            <div class="mx-auto flex max-w-6xl flex-col gap-10">
                <div class="flex flex-wrap justify-between gap-10">
                    <div class="flex max-w-[280px] flex-col gap-3">
                        <div class="flex items-center gap-2.5">
                            <img
                                src="/images/opportunity-scout-icon.png"
                                alt="Opportunity Scout"
                                class="size-6 rounded-md"
                            />
                            <span class="text-[15px] font-semibold">
                                Opportunity Scout
                            </span>
                        </div>
                        <p class="text-[13px] leading-relaxed text-[#656d76]">
                            Automated discovery of relevant Threads posts, built
                            on Laravel and the Threads Graph API.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-14">
                        <div class="flex flex-col gap-3">
                            <span
                                class="text-xs font-semibold tracking-wide text-[#8b949e] uppercase"
                            >
                                Product
                            </span>
                            <a
                                href="#features"
                                class="text-sm text-[#e6edf3] hover:text-[#58a6ff]"
                            >
                                Features
                            </a>
                            <a
                                href="#how-it-works"
                                class="text-sm text-[#e6edf3] hover:text-[#58a6ff]"
                            >
                                How it works
                            </a>
                            <a
                                href="#insights"
                                class="text-sm text-[#e6edf3] hover:text-[#58a6ff]"
                            >
                                Dashboard
                            </a>
                        </div>
                        <div class="flex flex-col gap-3">
                            <span
                                class="text-xs font-semibold tracking-wide text-[#8b949e] uppercase"
                            >
                                Account
                            </span>
                            <Link
                                :href="login()"
                                class="text-sm text-[#e6edf3] hover:text-[#58a6ff]"
                            >
                                Sign in
                            </Link>
                            <Link
                                :href="register()"
                                class="text-sm text-[#e6edf3] hover:text-[#58a6ff]"
                            >
                                Get started
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-[#30363d] pt-6"
                >
                    <span class="text-[13px] text-[#656d76]">
                        © {{ new Date().getFullYear() }} Opportunity Scout.
                    </span>
                    <nav class="flex items-center gap-4">
                        <Link
                            :href="privacy()"
                            class="text-[13px] text-[#656d76] hover:text-[#58a6ff]"
                        >
                            Privacy Policy
                        </Link>
                        <Link
                            :href="terms()"
                            class="text-[13px] text-[#656d76] hover:text-[#58a6ff]"
                        >
                            Terms of Service
                        </Link>
                        <Link
                            :href="dataDeletion()"
                            class="text-[13px] text-[#656d76] hover:text-[#58a6ff]"
                        >
                            Data deletion
                        </Link>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
