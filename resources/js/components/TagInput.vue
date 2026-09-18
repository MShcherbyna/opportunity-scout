<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const tags = defineModel<string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        id?: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'Add and press Enter…',
    },
);

const draft = ref('');

function addTag() {
    const value = draft.value.trim().toLowerCase();

    if (value && !tags.value.includes(value)) {
        tags.value = [...tags.value, value];
    }

    draft.value = '';
}

function removeTag(tag: string) {
    tags.value = tags.value.filter((existing) => existing !== tag);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag();
    } else if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        tags.value.length > 0
    ) {
        removeTag(tags.value[tags.value.length - 1]);
    }
}
</script>

<template>
    <div
        class="border-input focus-within:border-ring focus-within:ring-ring/50 flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border bg-transparent px-2 py-1.5 focus-within:ring-[3px]"
    >
        <Badge
            v-for="tag in tags"
            :key="tag"
            variant="secondary"
            class="gap-1 pr-1"
        >
            {{ tag }}
            <button
                type="button"
                class="hover:text-foreground rounded-full"
                :aria-label="`Remove ${tag}`"
                @click="removeTag(tag)"
            >
                <X class="size-3" />
            </button>
        </Badge>
        <input
            :id="props.id"
            v-model="draft"
            type="text"
            :placeholder="tags.length === 0 ? props.placeholder : ''"
            class="placeholder:text-muted-foreground min-w-32 flex-1 bg-transparent text-sm outline-none"
            @keydown="onKeydown"
            @blur="addTag"
        />
    </div>
</template>
