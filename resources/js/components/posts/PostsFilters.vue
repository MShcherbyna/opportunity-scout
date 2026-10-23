<script setup lang="ts">
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL_KEYWORDS = '__all__';

const search = defineModel<string>('search', { required: true });
const keyword = defineModel<string | null>('keyword', { required: true });

defineProps<{
    keywordOptions: string[];
}>();
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div class="relative w-full sm:w-48">
            <Label for="posts-search" class="sr-only">Search posts</Label>
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
            />
            <Input
                id="posts-search"
                :model-value="search"
                type="search"
                maxlength="100"
                placeholder="Search…"
                class="pl-8"
                @update:model-value="search = String($event)"
            />
        </div>

        <Label for="posts-keyword" class="sr-only">Keyword</Label>
        <Select
            :model-value="keyword ?? ALL_KEYWORDS"
            @update:model-value="
                keyword = $event === ALL_KEYWORDS ? null : String($event)
            "
        >
            <SelectTrigger id="posts-keyword" class="w-full sm:w-40">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL_KEYWORDS">All keywords</SelectItem>
                <SelectItem
                    v-for="option in keywordOptions"
                    :key="option"
                    :value="option"
                >
                    {{ option }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Label for="posts-sort" class="sr-only">Sort</Label>
        <Select model-value="newest">
            <SelectTrigger id="posts-sort" class="w-full sm:w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="newest">Newest first</SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
