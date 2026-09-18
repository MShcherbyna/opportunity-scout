import type { AiSettings, ScrapingSettings, TelegramSettings } from '@/types';

export const defaultScrapingSettings: ScrapingSettings = {
    checkFrequency: '15',
    postsPerRun: 100,
    keywords: ['php', 'laravel', 'vue', 'backend', 'remote'],
    regions: 'Europe, Ukraine, English',
    suggestedResponseTemplate:
        "Hi! I saw your post about {{position}}. I'm a PHP/Vue developer and would be interested in discussing the project.",
};

export const defaultAiSettings: AiSettings = {
    provider: 'openrouter',
    model: 'openai/gpt-5-mini',
    hasApiKey: false,
    relevanceThreshold: 75,
    filteringInstruction:
        'Approve posts where a person or company is looking for a PHP, Laravel, Vue or full-stack developer. Prioritize contract, freelance and remote work. Reject job-seeker posts, courses, spam and unrelated discussions.',
};

export const defaultTelegramSettings: TelegramSettings = {
    hasBotToken: false,
    chatId: '',
    minimumRelevance: 75,
    delivery: 'immediately',
};
