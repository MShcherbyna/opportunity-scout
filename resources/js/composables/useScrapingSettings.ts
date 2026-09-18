import type { Ref } from 'vue';
import { reactive, ref } from 'vue';
import { defaultScrapingSettings } from '@/data/settings';
import type { ScrapingSettings, SaveState } from '@/types';

// Module-level state so edits persist across navigation within the session,
// without a backend. This is frontend-only state, never sent anywhere.
const settings = reactive<ScrapingSettings>({ ...defaultScrapingSettings });
const saveState = ref<SaveState>('idle');

export type UseScrapingSettingsReturn = {
    settings: ScrapingSettings;
    saveState: Ref<SaveState>;
    save: () => Promise<void>;
};

export function useScrapingSettings(): UseScrapingSettingsReturn {
    async function save() {
        saveState.value = 'saving';

        await new Promise((resolve) => setTimeout(resolve, 600));

        saveState.value = 'success';
    }

    return {
        settings,
        saveState,
        save,
    };
}
