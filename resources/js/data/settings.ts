import type { AiSettings, TelegramSettings } from '@/types';

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
