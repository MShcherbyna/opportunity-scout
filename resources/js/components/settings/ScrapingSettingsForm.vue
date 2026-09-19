<script setup lang="ts">
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import TagInput from '@/components/TagInput.vue';
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
import { useScrapingSettings } from '@/composables/useScrapingSettings';
import type { CheckFrequency } from '@/types';

const { settings, saveState, save } = useScrapingSettings();

const templateVariables = ['{{position}}', '{{author}}', '{{post_url}}'];

const frequencyOptions: { value: CheckFrequency; label: string }[] = [
    { value: '5', label: 'Every 5 minutes' },
    { value: '15', label: 'Every 15 minutes' },
    { value: '30', label: 'Every 30 minutes' },
    { value: '60', label: 'Every hour' },
];

const postsPerRunError = computed(() => {
    if (settings.postsPerRun < 1 || settings.postsPerRun > 500) {
        return 'Enter a number between 1 and 500.';
    }

    return '';
});

const keywordsError = computed(() =>
    settings.keywords.length === 0
        ? 'Add at least one keyword to match against.'
        : '',
);

const isValid = computed(() => !postsPerRunError.value && !keywordsError.value);

async function handleSave() {
    if (!isValid.value) {
        return;
    }

    await save();
    toast.success('Scraping settings saved');
}
</script>

<template>
    <form
        class="border-border bg-card max-w-3xl rounded-lg border p-5"
        @submit.prevent="handleSave"
    >
        <h2 class="text-sm font-semibold">Search &amp; scraping</h2>
        <p class="text-muted-foreground text-xs">
            Configure how Opportunity Scout collects posts.
        </p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="check-frequency">Check frequency</Label>
                <Select v-model="settings.checkFrequency">
                    <SelectTrigger id="check-frequency" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in frequencyOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="posts-per-run">Posts per run</Label>
                <Input
                    id="posts-per-run"
                    v-model.number="settings.postsPerRun"
                    type="number"
                    min="1"
                    max="500"
                    :aria-invalid="!!postsPerRunError"
                />
                <p v-if="postsPerRunError" class="text-destructive text-xs">
                    {{ postsPerRunError }}
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-1.5">
            <Label for="keywords">Keywords</Label>
            <TagInput
                id="keywords"
                v-model="settings.keywords"
                placeholder="php, laravel, vue…"
            />
            <p v-if="keywordsError" class="text-destructive text-xs">
                {{ keywordsError }}
            </p>
        </div>

        <div class="mt-5 grid gap-1.5">
            <Label for="regions">Regions / languages</Label>
            <Input id="regions" v-model="settings.regions" />
        </div>

        <div class="mt-5 grid gap-1.5">
            <Label for="response-template"> Suggested response template </Label>
            <Textarea
                id="response-template"
                v-model="settings.suggestedResponseTemplate"
                class="min-h-28"
            />
            <p class="text-muted-foreground text-xs">
                Supports
                <code
                    v-for="(variable, index) in templateVariables"
                    :key="variable"
                    class="bg-muted rounded px-1 py-0.5"
                    >{{ variable
                    }}<template v-if="index < templateVariables.length - 1"
                        >,</template
                    ></code
                >
                variables.
            </p>
        </div>

        <Button
            type="submit"
            class="mt-5"
            :disabled="!isValid || saveState === 'saving'"
        >
            <Spinner v-if="saveState === 'saving'" />
            Save settings
        </Button>
    </form>
</template>
