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
import type { PostSortOption, PostStatusFilter } from '@/composables/usePosts';

const status = defineModel<PostStatusFilter>('status', { required: true });
const sort = defineModel<PostSortOption>('sort', { required: true });
const search = defineModel<string>('search', { required: true });

const statusOptions: { value: PostStatusFilter; label: string }[] = [
    { value: 'all', label: 'All relevance' },
    { value: 'relevant', label: 'Relevant' },
    { value: 'maybe', label: 'Maybe' },
    { value: 'rejected', label: 'Rejected' },
];

const sortOptions: { value: PostSortOption; label: string }[] = [
    { value: 'newest', label: 'Newest first' },
    { value: 'score', label: 'Highest score' },
];
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
                placeholder="Search…"
                class="pl-8"
                @update:model-value="search = String($event)"
            />
        </div>

        <Label for="posts-status" class="sr-only">Relevance</Label>
        <Select
            :model-value="status"
            @update:model-value="status = $event as PostStatusFilter"
        >
            <SelectTrigger id="posts-status" class="w-full sm:w-40">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="option in statusOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Label for="posts-sort" class="sr-only">Sort</Label>
        <Select
            :model-value="sort"
            @update:model-value="sort = $event as PostSortOption"
        >
            <SelectTrigger id="posts-sort" class="w-full sm:w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="option in sortOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
