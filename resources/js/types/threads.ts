export type PostStatus = 'relevant' | 'maybe' | 'rejected';

export type ThreadsConnectionStatus = 'connected' | 'disconnected';

export type ThreadsDisconnectReason =
    | 'user'
    | 'uninstalled'
    | 'token_invalid'
    | 'expired';

/** Shared Inertia prop `threadsConnection`. */
export type ThreadsConnectionSummary = {
    status: ThreadsConnectionStatus;
    disconnectReason: ThreadsDisconnectReason | null;
    username: string;
};

/** Prop `connection` of the Threads settings tab. */
export type ThreadsConnectionDetails = {
    username: string;
    name: string | null;
    status: ThreadsConnectionStatus;
    disconnectReason: ThreadsDisconnectReason | null;
    connectedAt: string;
    tokenExpiresAt: string | null;
};

export type SearchRunOutcome =
    | 'completed'
    | 'partial'
    | 'paused_quota'
    | 'budget_reached'
    | 'stopped_disconnected';

export type ManualRunBlockedReason =
    | 'not_connected'
    | 'no_keywords'
    | 'run_in_progress'
    | 'cooldown'
    | 'paused'
    | 'budget';

export type SearchStatus = {
    runState: 'idle' | 'queued' | 'running';
    lastRunId: number | null;
    lastRunFinishedAt: string | null;
    lastRunPostsStored: number | null;
    lastRunOutcome: SearchRunOutcome | null;
    nextRunAt: string | null;
    pausedUntil: string | null;
    manualRunBlockedReason: ManualRunBlockedReason | null;
    manualRunAvailableAt: string | null;
};

export type ThreadsPost = {
    id: number;
    threadsPostId: string;
    text: string | null;
    permalink: string | null;
    username: string;
    keyword: string;
    postedAt: string;
    foundAt: string;
};

export type PaginatedPosts = {
    data: ThreadsPost[];
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

export type PostFilters = {
    search: string;
    keyword: string | null;
};

export type ScrapingKeyword = {
    id: number;
    keyword: string;
    lastSearchedAt: string | null;
    lastResultCount: number | null;
    zeroResultsSince: string | null;
};

export type SearchSettings = {
    intervalMinutes: 5 | 15 | 30 | 60;
    firstSearchLookbackDays: 0 | 1 | 3;
};

export type SearchBudget = {
    dailyBudget: number;
    plannedDailyQueries: number;
    runsPerDay: number;
    keywordCap: number;
    keywordCount: number;
};

export type ThreadsQuota = {
    usedLast24Hours: number;
    remaining: number;
    dailyBudget: number;
    threadsDailyLimit: number;
};

export type DashboardThreadsStats = {
    postsFound: number;
    foundLast7Days: number;
    foundToday: number;
};

export type FoundActivityPoint = {
    date: string;
    found: number;
};

export type DeletionRequest = {
    confirmationCode: string;
    status: 'pending' | 'completed';
    requestedAt: string;
    completedAt: string | null;
};

/** Mock-only dashboard values (the relevant-posts card and series stay mock, AC-105). */
export type ActivityPoint = {
    label: string;
    found: number;
    relevant: number;
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

export type SaveState = 'idle' | 'saving' | 'success' | 'error';
