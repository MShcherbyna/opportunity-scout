<script setup lang="ts">
import { CheckCircle2, XCircle } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAiSettings } from '@/composables/useAiSettings';
import type { AiProvider } from '@/types';

const {
    settings,
    saveState,
    testStatus,
    testMessage,
    testConfiguration,
    save,
} = useAiSettings();

// The key only ever lives in this local ref, for the duration of the test
// or save action. It is never persisted to app state, storage, or a network
// request — only a boolean `hasApiKey` flag survives past this component.
const apiKeyDraft = ref('');

const providerOptions: { value: AiProvider; label: string }[] = [
    { value: 'openrouter', label: 'OpenRouter' },
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'custom', label: 'Custom OpenAI-compatible' },
];

async function handleTest() {
    await testConfiguration(apiKeyDraft.value);
}

async function handleSave() {
    await save(apiKeyDraft.value);
    apiKeyDraft.value = '';
    toast.success('AI filtering settings saved');
}
</script>

<template>
    <form
        class="border-border bg-card max-w-3xl rounded-lg border p-5"
        @submit.prevent="handleSave"
    >
        <h2 class="text-sm font-semibold">AI filtering model</h2>
        <p class="text-muted-foreground text-xs">
            Connect a model that scores whether a collected post is useful.
        </p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="ai-provider">Provider</Label>
                <Select v-model="settings.provider">
                    <SelectTrigger id="ai-provider" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in providerOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="ai-model">Model</Label>
                <Input
                    id="ai-model"
                    v-model="settings.model"
                    placeholder="openai/gpt-5-mini"
                />
            </div>
        </div>

        <div class="mt-5 grid gap-1.5">
            <Label for="ai-api-key">API key</Label>
            <Input
                id="ai-api-key"
                v-model="apiKeyDraft"
                type="password"
                autocomplete="off"
                :placeholder="
                    settings.hasApiKey
                        ? 'Key saved — enter a new one to replace it'
                        : '••••••••••••••••'
                "
            />
            <p class="text-muted-foreground text-xs">
                Stored encrypted on the backend. This mock UI never sends or
                persists it on the client.
            </p>
        </div>

        <div class="mt-5 grid gap-1.5">
            <div class="flex items-center justify-between">
                <Label for="ai-threshold">Relevance threshold</Label>
                <span class="text-muted-foreground text-xs"
                    >{{ settings.relevanceThreshold }}%</span
                >
            </div>
            <input
                id="ai-threshold"
                v-model.number="settings.relevanceThreshold"
                type="range"
                min="0"
                max="100"
                step="5"
                class="accent-primary"
            />
        </div>

        <div class="mt-5 grid gap-1.5">
            <Label for="ai-instruction">Filtering instruction</Label>
            <Textarea
                id="ai-instruction"
                v-model="settings.filteringInstruction"
                class="min-h-32"
            />
        </div>

        <div
            v-if="testStatus !== 'idle'"
            class="mt-4 flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
            :class="
                testStatus === 'success'
                    ? 'border-primary/30 bg-primary/5 text-primary'
                    : testStatus === 'error'
                      ? 'border-destructive/30 bg-destructive/5 text-destructive'
                      : 'border-border text-muted-foreground'
            "
            role="status"
        >
            <Spinner v-if="testStatus === 'testing'" class="size-4" />
            <CheckCircle2 v-else-if="testStatus === 'success'" class="size-4" />
            <XCircle v-else-if="testStatus === 'error'" class="size-4" />
            <span>{{
                testStatus === 'testing'
                    ? 'Testing configuration…'
                    : testMessage
            }}</span>
        </div>

        <div class="mt-5 flex gap-2">
            <Button
                type="button"
                variant="outline"
                :disabled="testStatus === 'testing'"
                @click="handleTest"
            >
                <Spinner v-if="testStatus === 'testing'" />
                Test configuration
            </Button>
            <Button type="submit" :disabled="saveState === 'saving'">
                <Spinner v-if="saveState === 'saving'" />
                Save AI settings
            </Button>
        </div>
    </form>
</template>
