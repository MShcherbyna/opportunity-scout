<script setup lang="ts">
import { computed, ref } from 'vue';
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
import { useTelegramSettings } from '@/composables/useTelegramSettings';
import type { NotificationDelivery } from '@/types';

const { settings, saveState, save } = useTelegramSettings();

// Never stored beyond this component — only `hasBotToken` persists.
const botTokenDraft = ref('');

const minimumRelevanceOptions = [
    { value: '75', label: '75% and higher' },
    { value: '85', label: '85% and higher' },
    { value: '0', label: 'All matched posts' },
];

const deliveryOptions: { value: NotificationDelivery; label: string }[] = [
    { value: 'immediately', label: 'Immediately' },
    { value: 'digest_hourly', label: 'Digest every hour' },
];

const chatIdError = computed(() =>
    settings.chatId && !/^-?\d+$/.test(settings.chatId)
        ? 'Chat ID should be a numeric Telegram chat identifier.'
        : '',
);

async function handleSave() {
    if (chatIdError.value) {
        return;
    }

    await save(botTokenDraft.value);
    botTokenDraft.value = '';
    toast.success('Telegram settings saved');
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <form
            class="border-border bg-card rounded-lg border p-5"
            @submit.prevent="handleSave"
        >
            <h2 class="text-sm font-semibold">Telegram notifications</h2>
            <p class="text-muted-foreground text-xs">
                Push relevant opportunities to your bot.
            </p>

            <div class="mt-5 grid gap-1.5">
                <Label for="bot-token">Bot token</Label>
                <Input
                    id="bot-token"
                    v-model="botTokenDraft"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        settings.hasBotToken
                            ? 'Token saved — enter a new one to replace it'
                            : '••••••••••••'
                    "
                />
            </div>

            <div class="mt-5 grid gap-1.5">
                <Label for="chat-id">Chat ID</Label>
                <Input
                    id="chat-id"
                    v-model="settings.chatId"
                    placeholder="123456789"
                    :aria-invalid="!!chatIdError"
                />
                <p v-if="chatIdError" class="text-destructive text-xs">
                    {{ chatIdError }}
                </p>
            </div>

            <Button
                type="submit"
                class="mt-5"
                :disabled="saveState === 'saving' || !!chatIdError"
            >
                <Spinner v-if="saveState === 'saving'" />
                Save Telegram
            </Button>
        </form>

        <div class="border-border bg-card rounded-lg border p-5">
            <h2 class="text-sm font-semibold">Notification rules</h2>

            <div class="mt-5 grid gap-1.5">
                <Label for="min-relevance">Minimum relevance</Label>
                <Select
                    :model-value="String(settings.minimumRelevance)"
                    @update:model-value="
                        settings.minimumRelevance = Number($event)
                    "
                >
                    <SelectTrigger id="min-relevance" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in minimumRelevanceOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="mt-5 grid gap-1.5">
                <Label for="delivery">Delivery</Label>
                <Select v-model="settings.delivery">
                    <SelectTrigger id="delivery" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in deliveryOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </div>
</template>
