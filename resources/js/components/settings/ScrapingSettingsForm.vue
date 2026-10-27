<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/settings/searchSettings';
import type { SearchSettings } from '@/types';

const props = defineProps<{
    searchSettings: SearchSettings;
}>();

const form = useForm({
    interval_minutes: String(props.searchSettings.intervalMinutes),
    first_search_lookback_days: String(
        props.searchSettings.firstSearchLookbackDays,
    ),
});

const intervalOptions = [
    { value: '5', label: 'Every 5 minutes' },
    { value: '15', label: 'Every 15 minutes' },
    { value: '30', label: 'Every 30 minutes' },
    { value: '60', label: 'Every hour' },
];

const lookbackOptions = [
    { value: '0', label: 'No lookback' },
    { value: '1', label: 'Last 1 day' },
    { value: '3', label: 'Last 3 days' },
];

function save() {
    form.transform((data) => ({
        interval_minutes: Number(data.interval_minutes),
        first_search_lookback_days: Number(data.first_search_lookback_days),
    })).patch(update.url(), { preserveScroll: true });
}
</script>

<template>
    <form
        class="border-border bg-card max-w-3xl rounded-lg border p-5"
        @submit.prevent="save"
    >
        <h2 class="text-sm font-semibold">Search settings</h2>
        <p class="text-muted-foreground text-xs">
            Configure how often Opportunity Scout searches Threads.
        </p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="search-interval">Search interval</Label>
                <Select v-model="form.interval_minutes">
                    <SelectTrigger id="search-interval" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in intervalOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="form.errors.interval_minutes"
                    class="text-destructive text-xs"
                >
                    {{ form.errors.interval_minutes }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <Label for="search-lookback"
                    >First search of a new keyword</Label
                >
                <Select v-model="form.first_search_lookback_days">
                    <SelectTrigger id="search-lookback" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in lookbackOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="form.errors.first_search_lookback_days"
                    class="text-destructive text-xs"
                >
                    {{ form.errors.first_search_lookback_days }}
                </p>
            </div>
        </div>

        <Button type="submit" class="mt-5" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Save settings
        </Button>
    </form>
</template>
