<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Info, TriangleAlert, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

const budgetDialogOpen = ref(false);

const searchBudgetParagraphs = [
    'Opportunity Scout plans with 2,000 Threads searches per 24 hours. Threads allows 2,200 for your account, shared with other apps you use with Threads, so we keep some headroom.',
    'Each run searches every keyword once, so one run uses as many searches as you have keywords. Runs per day = 1,440 minutes ÷ your interval.',
    'Keyword limit = the smaller of 20 and 2,000 ÷ runs per day: every 5 minutes (288 runs) → 6 keywords; every 15 minutes (96 runs) → 20; every 30 minutes (48 runs) → 20; every hour (24 runs) → 20.',
];

const page = usePage();

const isConnected = computed(
    () => page.props.threadsConnection?.status === 'connected',
);

const atCap = computed(
    () => props.searchBudget.keywordCount >= props.searchBudget.keywordCap,
);

const newKeywordsInput = ref('');
const savingKeywords = ref(false);
const keywordErrors = ref<string[]>([]);
const renameForm = useForm({ keyword: '' });
const editingId = ref<number | null>(null);
const renameInputs = ref<HTMLInputElement[]>([]);

/** Splits "php, laravel, vue" into trimmed, deduplicated candidates. */
function splitKeywordCandidates(input: string): string[] {
    return Array.from(
        new Set(
            input
                .split(',')
                .map((candidate) => candidate.trim())
                .filter((candidate) => candidate.length > 0),
        ),
    );
}

function postKeyword(keyword: string): Promise<void> {
    return new Promise((resolve) => {
        router.post(
            store.url(),
            { keyword },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    if (errors.keyword) {
                        keywordErrors.value.push(
                            `${keyword}: ${errors.keyword}`,
                        );
                    }
                },
                onFinish: () => resolve(),
            },
        );
    });
}

async function saveKeywords() {
    if (savingKeywords.value) {
        return;
    }

    const candidates = splitKeywordCandidates(newKeywordsInput.value);

    if (candidates.length === 0) {
        return;
    }

    savingKeywords.value = true;
    keywordErrors.value = [];

    for (const candidate of candidates) {
        if (atCap.value) {
            keywordErrors.value.push(`${candidate}: keyword limit reached.`);
            continue;
        }

        await postKeyword(candidate);
    }

    savingKeywords.value = false;
    newKeywordsInput.value = '';
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
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Keywords</h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="budgetDialogOpen = true"
                >
                    <Info class="size-3.5" />
                    How does this work?
                </Button>
            </div>
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
                <Label for="new-keyword">Add keywords</Label>
                <div class="flex gap-2">
                    <Input
                        id="new-keyword"
                        v-model="newKeywordsInput"
                        type="text"
                        placeholder="php, laravel, vue…"
                        :disabled="atCap || savingKeywords"
                        :aria-invalid="keywordErrors.length > 0"
                        @keydown.enter.prevent="saveKeywords"
                    />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="
                            atCap ||
                            savingKeywords ||
                            newKeywordsInput.trim().length === 0
                        "
                        @click="saveKeywords"
                    >
                        Save
                    </Button>
                </div>
                <p
                    v-for="error in keywordErrors"
                    :key="error"
                    class="text-destructive text-xs"
                >
                    {{ error }}
                </p>
                <p class="text-muted-foreground text-xs">
                    Separate multiple keywords with commas — each one is saved
                    as its own keyword.
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

        <Dialog v-model:open="budgetDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Search budget</DialogTitle>
                </DialogHeader>
                <p
                    v-for="paragraph in searchBudgetParagraphs"
                    :key="paragraph"
                    class="text-muted-foreground text-sm"
                >
                    {{ paragraph }}
                </p>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Close</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TooltipProvider>
</template>
