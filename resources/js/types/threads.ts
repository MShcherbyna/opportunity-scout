export type PostStatus = 'relevant' | 'maybe' | 'rejected';

export type Post = {
    id: number;
    threadsUrl: string;
    author: string;
    content: string;
    status: PostStatus;
    relevanceScore: number;
    relevanceReasons: string[];
    foundAt: string;
    publishedAt: string;
    suggestedResponse: string;
    /** User-driven "mark as relevant" toggle, independent of the AI-assigned status/score. */
    manuallyMarkedRelevant: boolean;
};

export type DashboardStats = {
    postsCollected: { value: number; change: string };
    relevantPosts: { value: number; change: string };
    newToday: { value: number; change: string };
};

export type ActivityPoint = {
    label: string;
    collected: number;
    relevant: number;
};

export type CheckFrequency = '5' | '15' | '30' | '60';

export type ScrapingSettings = {
    checkFrequency: CheckFrequency;
    postsPerRun: number;
    keywords: string[];
    regions: string;
    suggestedResponseTemplate: string;
};

export type AiProvider = 'openrouter' | 'openai' | 'anthropic' | 'custom';

export type AiTestStatus = 'idle' | 'testing' | 'success' | 'error';

export type AiSettings = {
    provider: AiProvider;
    model: string;
    hasApiKey: boolean;
    relevanceThreshold: number;
    filteringInstruction: string;
};

export type NotificationDelivery = 'immediately' | 'digest_hourly';

export type TelegramSettings = {
    hasBotToken: boolean;
    chatId: string;
    minimumRelevance: number;
    delivery: NotificationDelivery;
};

export type ThreadsAccount = {
    username: string;
    displayName: string;
    connectedAt: string;
    tokenExpiresAt: string;
};

export type ThreadsConnectionState = 'idle' | 'connecting' | 'disconnecting';

export type SaveState = 'idle' | 'saving' | 'success' | 'error';
