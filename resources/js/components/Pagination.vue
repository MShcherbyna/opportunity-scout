<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    currentPage: number;
    totalPages: number;
    totalItems: number;
    perPage: number;
}>();

const emit = defineEmits<{
    (e: 'update:currentPage', page: number): void;
}>();

type PageItem = number | 'ellipsis';

const pageItems = computed<PageItem[]>(() => {
    const { currentPage, totalPages } = props;

    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const items = new Set<number>([
        1,
        totalPages,
        currentPage,
        currentPage - 1,
        currentPage + 1,
    ]);

    const sorted = [...items]
        .filter((n) => n >= 1 && n <= totalPages)
        .sort((a, b) => a - b);

    const result: PageItem[] = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            result.push('ellipsis');
        }

        result.push(page);
    });

    return result;
});

const rangeStart = computed(() =>
    props.totalItems === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1,
);
const rangeEnd = computed(() =>
    Math.min(props.currentPage * props.perPage, props.totalItems),
);

function goTo(page: number) {
    if (page < 1 || page > props.totalPages || page === props.currentPage) {
        return;
    }

    emit('update:currentPage', page);
}
</script>

<template>
    <nav
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
        aria-label="Pagination"
    >
        <p class="text-muted-foreground text-xs">
            Showing {{ rangeStart }}–{{ rangeEnd }} of {{ totalItems }}
        </p>

        <div class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon-sm"
                :disabled="currentPage <= 1"
                aria-label="Previous page"
                @click="goTo(currentPage - 1)"
            >
                <ChevronLeft class="size-4" />
            </Button>

            <template v-for="(item, index) in pageItems" :key="index">
                <span
                    v-if="item === 'ellipsis'"
                    class="text-muted-foreground px-1.5 text-xs"
                    >…</span
                >
                <Button
                    v-else
                    :variant="item === currentPage ? 'default' : 'outline'"
                    size="icon-sm"
                    :aria-current="item === currentPage ? 'page' : undefined"
                    :aria-label="`Page ${item}`"
                    @click="goTo(item)"
                >
                    {{ item }}
                </Button>
            </template>

            <Button
                variant="outline"
                size="icon-sm"
                :disabled="currentPage >= totalPages"
                aria-label="Next page"
                @click="goTo(currentPage + 1)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>
    </nav>
</template>
