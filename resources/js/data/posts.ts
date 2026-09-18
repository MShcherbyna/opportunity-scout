import type { Post, PostStatus } from '@/types';

type PostSeed = {
    author: string;
    content: string;
    status: PostStatus;
    relevanceScore: number;
    relevanceReasons: string[];
    suggestedResponse: string;
    minutesAgo: number;
};

const seeds: PostSeed[] = [
    {
        author: '@devfounder',
        content:
            'Looking for a PHP/Laravel developer for a remote SaaS project. We’re looking for an experienced PHP/Laravel developer to help with a growing SaaS product. Work includes API integrations, queues, PostgreSQL and some Vue dashboard tasks. Remote, EU timezone preferred.',
        status: 'relevant',
        relevanceScore: 92,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches PHP, Laravel and Vue keywords',
            'Remote contract-style opportunity',
        ],
        suggestedResponse:
            "Hi! I saw your post about the Laravel SaaS project. I'm a PHP/Vue developer and would be interested in discussing the project. Could you share more details?",
        minutesAgo: 4,
    },
    {
        author: '@startupceo',
        content:
            'Need help building an internal dashboard in Vue + PHP. Small team, fast pace, looking for someone who can own the frontend and backend end to end.',
        status: 'relevant',
        relevanceScore: 87,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches Vue and PHP keywords',
            'Describes ongoing contract work',
        ],
        suggestedResponse:
            "Hi! I saw you're looking for help with an internal dashboard in Vue + PHP. I'd love to hear more about the scope and timeline.",
        minutesAgo: 18,
    },
    {
        author: '@buildfast',
        content:
            'Hiring freelance backend engineer — EU timezone preferred. Must be comfortable with Laravel, queues, and third-party API integrations.',
        status: 'relevant',
        relevanceScore: 79,
        relevanceReasons: [
            'Freelance backend hiring post',
            'Matches Laravel keyword',
            'EU timezone matches configured region',
        ],
        suggestedResponse:
            "Hi! I'm a Laravel backend developer based in the EU timezone — happy to share more about my experience with queues and API integrations.",
        minutesAgo: 36,
    },
    {
        author: '@founder_one',
        content:
            'PHP developer needed for API integrations. Remote, contract opportunity, 3-6 months with possible extension.',
        status: 'relevant',
        relevanceScore: 94,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches PHP keyword',
            'Remote contract-style opportunity',
        ],
        suggestedResponse:
            "Hi! I saw you're hiring a PHP developer for API integrations — I'd love to learn more about the project.",
        minutesAgo: 55,
    },
    {
        author: '@productteam',
        content:
            'Vue freelancer for dashboard redesign. Looking for someone with strong component architecture skills.',
        status: 'relevant',
        relevanceScore: 86,
        relevanceReasons: [
            'Explicitly hiring a freelancer',
            'Matches Vue keyword',
        ],
        suggestedResponse:
            'Hi! I saw your post about the dashboard redesign — I specialize in Vue component architecture and would love to help.',
        minutesAgo: 70,
    },
    {
        author: '@agency_owner',
        content:
            'Looking for senior frontend engineer to join our agency on a trial contract basis.',
        status: 'maybe',
        relevanceScore: 61,
        relevanceReasons: [
            'Hiring post, but framework not specified',
            'Agency work rather than direct client work',
        ],
        suggestedResponse:
            "Hi! I'd be interested in learning more about the frontend engineer role — could you share more about the tech stack?",
        minutesAgo: 95,
    },
    {
        author: '@saasdaily',
        content:
            'Laravel developer for SaaS maintenance. Ongoing part-time work, a few hours a week.',
        status: 'relevant',
        relevanceScore: 91,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches Laravel keyword',
            'Ongoing remote contract',
        ],
        suggestedResponse:
            "Hi! I saw you're looking for ongoing Laravel maintenance help — I'd be glad to discuss the scope.",
        minutesAgo: 130,
    },
    {
        author: '@marketmaker',
        content:
            'Backend engineer for marketplace. Must have experience with high-traffic PHP applications.',
        status: 'relevant',
        relevanceScore: 88,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches backend and PHP keywords',
        ],
        suggestedResponse:
            'Hi! I saw your post about the marketplace backend role — I have experience with high-traffic PHP applications and would love to chat.',
        minutesAgo: 340,
    },
    {
        author: '@remotejobsdaily',
        content:
            '10 tips for landing your first remote developer job in 2026. Thread 🧵',
        status: 'rejected',
        relevanceScore: 12,
        relevanceReasons: [
            'Career advice thread, not a hiring post',
            'No one is looking to hire',
        ],
        suggestedResponse: '',
        minutesAgo: 400,
    },
    {
        author: '@coursebot',
        content:
            'New course: Master Laravel in 30 days. Link in bio, use code THREADS10 for a discount.',
        status: 'rejected',
        relevanceScore: 4,
        relevanceReasons: ['Course promotion / spam', 'Not a hiring post'],
        suggestedResponse: '',
        minutesAgo: 480,
    },
    {
        author: '@juniordev22',
        content:
            'Looking for my first job as a junior PHP developer, open to remote or hybrid roles.',
        status: 'rejected',
        relevanceScore: 18,
        relevanceReasons: [
            'Job-seeker post, not a hiring post',
            'Instruction rejects job-seeker posts',
        ],
        suggestedResponse: '',
        minutesAgo: 520,
    },
    {
        author: '@growthagency',
        content:
            'We help SaaS companies scale with Laravel. Book a free consultation this week.',
        status: 'rejected',
        relevanceScore: 9,
        relevanceReasons: ['Agency self-promotion', 'Not a hiring post'],
        suggestedResponse: '',
        minutesAgo: 600,
    },
    {
        author: '@teamlead_kate',
        content:
            'Hiring a full-stack developer (Laravel + Vue) for a fintech product. Remote, competitive pay.',
        status: 'relevant',
        relevanceScore: 96,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches Laravel and Vue keywords',
            'Remote contract-style opportunity',
        ],
        suggestedResponse:
            'Hi! I saw your post about the fintech full-stack role — I have strong Laravel and Vue experience and would love to discuss.',
        minutesAgo: 640,
    },
    {
        author: '@indiehacker_sam',
        content:
            'Need a contractor to fix a few bugs in my Laravel app this week, small scope.',
        status: 'maybe',
        relevanceScore: 58,
        relevanceReasons: [
            'Hiring post, but very small scope',
            'Matches Laravel keyword',
        ],
        suggestedResponse:
            'Hi! Happy to take a look at the bugs in your Laravel app — could you share more details on the scope?',
        minutesAgo: 700,
    },
    {
        author: '@remoteworksdaily',
        content:
            'PHP backend developer wanted for a logistics platform. Long-term remote contract.',
        status: 'relevant',
        relevanceScore: 83,
        relevanceReasons: [
            'Explicitly hiring a developer',
            'Matches PHP and backend keywords',
            'Remote contract-style opportunity',
        ],
        suggestedResponse:
            "Hi! I saw you're hiring a PHP backend developer for a logistics platform — I'd love to hear more.",
        minutesAgo: 760,
    },
];

function formatRelativeTime(minutesAgo: number): string {
    if (minutesAgo < 60) {
        return `${minutesAgo} min ago`;
    }

    const hours = Math.floor(minutesAgo / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);

    return days === 1 ? 'Yesterday' : `${days}d ago`;
}

const now = Date.now();

function buildPost(seed: PostSeed, index: number): Post {
    const foundAtDate = new Date(now - seed.minutesAgo * 60_000);

    return {
        id: index + 1,
        threadsUrl: `https://www.threads.net/@${seed.author.replace('@', '')}/post/mock-${index + 1}`,
        author: seed.author,
        content: seed.content,
        status: seed.status,
        relevanceScore: seed.relevanceScore,
        relevanceReasons: seed.relevanceReasons,
        suggestedResponse: seed.suggestedResponse,
        foundAt: formatRelativeTime(seed.minutesAgo),
        publishedAt: foundAtDate.toISOString(),
    };
}

/**
 * The design reference shows 128 total collected posts. We hand-author a
 * realistic sample and pad the remainder with deterministic variations of
 * that sample so pagination has representative data without a backend.
 */
const TOTAL_MOCK_POSTS = 128;

function buildMockPosts(): Post[] {
    const posts: Post[] = seeds.map(buildPost);

    let index = posts.length;

    while (index < TOTAL_MOCK_POSTS) {
        const seed = seeds[index % seeds.length];
        const cycle = Math.floor(index / seeds.length);

        posts.push(
            buildPost(
                {
                    ...seed,
                    minutesAgo: seed.minutesAgo + cycle * 900,
                },
                index,
            ),
        );

        index += 1;
    }

    return posts;
}

export const mockPosts: Post[] = buildMockPosts();

export function findPostById(id: number): Post | undefined {
    return mockPosts.find((post) => post.id === id);
}
