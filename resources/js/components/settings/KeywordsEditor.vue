<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { TriangleAlert, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateTime } from '@/lib/threadsCopy';
import { destroy, store, update } from '@/routes/settings/keywords';
import { threads } from '@/routes/settings';
import type { ScrapingKeyword, SearchBudget, SearchSettings } from '@/types';

const props = defineProps<{
    keywords: ScrapingKeyword[];
    searchSettings: SearchSettings;
    searchBudget: SearchBudget;
    ownPostsOnly: boolean;
}>();

const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;

const page = usePage();

const isConnected = computed(
    () => page.props.threadsConnection?.status === 'connected',
);

const atCap = computed(
    () => props.searchBudget.keywordCount >= props.searchBudget.keywordCap,
);

const addForm = useForm({ keyword: '' });
const renameForm = useForm({ keyword: '' });
const editingId = ref<number | null>(null);
const renameInputs = ref<HTMLInputElement[]>([]);

function addKeyword() {
    if (atCap.value || addForm.processing) {
        return;
    }

    addForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

function startRename(keyword: ScrapingKeyword) {
    renameForm.clearErrors();
    renameForm.keyword = keyword.keyword;
    editingId.value = keyword.id;

    void nextTick(() => renameInputs.value[0]?.focus());
}

function cancelRename() {
    editingId.value = null;
    renameForm.reset();
    renameForm.clearErrors();
}

function saveRename(keyword: ScrapingKeyword) {
    if (renameForm.processing) {
        return;
    }

    if (renameForm.keyword === keyword.keyword) {
        cancelRename();

        return;
    }

    renameForm.patch(update.url(keyword.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            renameForm.reset();
        },
    });
}

function removeKeyword(keyword: ScrapingKeyword) {
    if (editingId.value === keyword.id) {
        cancelRename();
    }

    router.delete(destroy(keyword.id), { preserveScroll: true });
}

function hasNoResultsForSevenDays(keyword: ScrapingKeyword): boolean {
    return (
        !!keyword.zeroResultsSince &&
        new Date(keyword.zeroResultsSince).getTime() <=
            Date.now() - SEVEN_DAYS_MS
    );
}

function searchedText(keyword: ScrapingKeyword): string {
    if (!keyword.lastSearchedAt) {
        return 'Not searched yet';
    }

    return `Last searched ${formatDateTime(keyword.lastSearchedAt)} · ${keyword.lastResultCount ?? 0} posts`;
}
</script>

<template>
    <TooltipProvider>
        <div class="border-border bg-card max-w-3xl rounded-lg border p-5">
            <h2 class="text-sm font-semibold">Keywords</h2>
            <p class="text-muted-foreground text-xs">
                Opportunity Scout searches Threads for each keyword.
            </p>

            <p
                v-if="!isConnected"
                class="border-border bg-muted/50 mt-4 rounded-md border p-3 text-sm"
            >
                Threads is not connected. Keywords are saved but not searched
                until you connect.
                <Link :href="threads()" class="font-medium underline">
                    Connect Threads
                </Link>
            </p>

            <p
                v-if="ownPostsOnly"
                class="border-border bg-muted/50 mt-4 rounded-md border p-3 text-sm"
            >
                Until Meta approves search access, results include only your own
                Threads posts.
            </p>

            <ul v-if="keywords.length > 0" class="mt-4 flex flex-wrap gap-2">
                <li v-for="keyword in keywords" :key="keyword.id">
                    <div v-if="editingId === keyword.id" class="grid gap-1">
                        <Label :for="`rename-${keyword.id}`" class="sr-only">
                            Rename keyword
                        </Label>
                        <input
                            :id="`rename-${keyword.id}`"
                            ref="renameInputs"
                            v-model="renameForm.keyword"
                            type="text"
                            maxlength="100"
                            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-7 rounded-md border bg-transparent px-2 text-xs outline-none focus-visible:ring-[3px]"
                            :aria-invalid="!!renameForm.errors.keyword"
                            @keydown.enter.prevent="saveRename(keyword)"
                            @keydown.esc.prevent="cancelRename"
                        />
                        <p
                            v-if="renameForm.errors.keyword"
                            class="text-destructive text-xs"
                        >
                            {{ renameForm.errors.keyword }}
                        </p>
                    </div>

                    <Badge v-else variant="secondary" class="gap-1 pr-1">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 rounded-sm"
                                    @click="startRename(keyword)"
                                >
                                    <TriangleAlert
                                        v-if="hasNoResultsForSevenDays(keyword)"
                                        class="size-3 text-amber-600 dark:text-amber-400"
                                    />
                                    {{ keyword.keyword }}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ searchedText(keyword) }}</p>
                                <p v-if="hasNoResultsForSevenDays(keyword)">
                                    No results for 7 days. Threads hides results
                                    for some keywords it treats as sensitive.
                                </p>
                            </TooltipContent>
                        </Tooltip>
                        <button
                            type="button"
                            class="hover:text-foreground rounded-full"
                            :aria-label="`Remove ${keyword.keyword}`"
                            @click="removeKeyword(keyword)"
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>
                </li>
            </ul>

            <div class="mt-4 grid gap-1.5">
                <Label for="new-keyword">Add keyword</Label>
                <Input
                    id="new-keyword"
                    v-model="addForm.keyword"
                    type="text"
                    maxlength="100"
                    placeholder="php, laravel, vue…"
                    :disabled="atCap"
                    :aria-invalid="!!addForm.errors.keyword"
                    @keydown.enter.prevent="addKeyword"
                />
                <p
                    v-if="addForm.errors.keyword"
                    class="text-destructive text-xs"
                >
                    {{ addForm.errors.keyword }}
                </p>
                <p v-if="atCap" class="text-muted-foreground text-xs">
                    Keyword limit reached ({{
                        searchBudget.keywordCap
                    }}
                    keywords for a {{ searchSettings.intervalMinutes }}-minute
                    interval).
                </p>
                <p class="text-muted-foreground text-xs">
                    Threads search doesn't translate. Add each keyword in every
                    language you need, for example freelancer, фрілансер.
                </p>
                <p class="text-muted-foreground text-xs">
                    Uses {{ searchBudget.plannedDailyQueries }} of
                    {{ searchBudget.dailyBudget.toLocaleString('en') }} daily
                    Threads searches ({{ searchBudget.keywordCount }} keywords ×
                    {{ searchBudget.runsPerDay }} runs a day).
                </p>
            </div>
        </div>
    </TooltipProvider>
</template>
