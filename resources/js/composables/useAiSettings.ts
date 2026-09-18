import type { Ref } from 'vue';
import { reactive, ref } from 'vue';
import { defaultAiSettings } from '@/data/settings';
import type { AiSettings, AiTestStatus, SaveState } from '@/types';

// Module-level state so edits persist across navigation within the session.
// The API key itself is intentionally never stored here or anywhere else on
// the client — only a `hasApiKey` flag is kept, and test/save calls take the
// raw key as a local parameter that is discarded once used.
const settings = reactive<AiSettings>({ ...defaultAiSettings });
const saveState = ref<SaveState>('idle');
const testStatus = ref<AiTestStatus>('idle');
const testMessage = ref('');

export type UseAiSettingsReturn = {
    settings: AiSettings;
    saveState: Ref<SaveState>;
    testStatus: Ref<AiTestStatus>;
    testMessage: Ref<string>;
    testConfiguration: (apiKey: string) => Promise<void>;
    save: (apiKey: string) => Promise<void>;
};

export function useAiSettings(): UseAiSettingsReturn {
    async function testConfiguration(apiKey: string) {
        testStatus.value = 'testing';
        testMessage.value = '';

        await new Promise((resolve) => setTimeout(resolve, 900));

        if (!apiKey.trim()) {
            testStatus.value = 'error';
            testMessage.value =
                'Add an API key before testing the configuration.';

            return;
        }

        if (!settings.model.trim()) {
            testStatus.value = 'error';
            testMessage.value = 'Model is required to test the connection.';

            return;
        }

        testStatus.value = 'success';
        testMessage.value = `Connected to ${settings.model} — mock response looked valid.`;
    }

    async function save(apiKey: string) {
        saveState.value = 'saving';

        await new Promise((resolve) => setTimeout(resolve, 600));

        if (apiKey.trim()) {
            settings.hasApiKey = true;
        }

        saveState.value = 'success';
    }

    return {
        settings,
        saveState,
        testStatus,
        testMessage,
        testConfiguration,
        save,
    };
}
