import type { Ref } from 'vue';
import { reactive, ref } from 'vue';
import { defaultTelegramSettings } from '@/data/settings';
import type { SaveState, TelegramSettings } from '@/types';

// Module-level state so edits persist across navigation within the session.
// The bot token is never stored here — only a `hasBotToken` flag.
const settings = reactive<TelegramSettings>({ ...defaultTelegramSettings });
const saveState = ref<SaveState>('idle');

export type UseTelegramSettingsReturn = {
    settings: TelegramSettings;
    saveState: Ref<SaveState>;
    save: (botToken: string) => Promise<void>;
};

export function useTelegramSettings(): UseTelegramSettingsReturn {
    async function save(botToken: string) {
        saveState.value = 'saving';

        await new Promise((resolve) => setTimeout(resolve, 600));

        if (botToken.trim()) {
            settings.hasBotToken = true;
        }

        saveState.value = 'success';
    }

    return {
        settings,
        saveState,
        save,
    };
}
