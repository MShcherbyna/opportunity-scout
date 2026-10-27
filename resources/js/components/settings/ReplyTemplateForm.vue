<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/settings/replyTemplate';

const props = defineProps<{
    replyTemplate: string | null;
}>();

const placeholder = "Thanks for sharing, I'd love to help — DM me!";
const helperText =
    'Pre-fills the reply box when you tap Reply in Threads on a post. You still review and send it there.';

const form = useForm({
    reply_template: props.replyTemplate ?? '',
});

function save() {
    form.patch(update.url(), { preserveScroll: true });
}
</script>

<template>
    <form
        class="border-border bg-card max-w-3xl rounded-lg border p-5"
        @submit.prevent="save"
    >
        <h2 class="text-sm font-semibold">Reply template</h2>
        <p class="text-muted-foreground text-xs">{{ helperText }}</p>

        <div class="mt-5 grid gap-1.5">
            <Label for="reply-template">Reply template</Label>
            <Textarea
                id="reply-template"
                v-model="form.reply_template"
                class="min-h-32"
                :placeholder="placeholder"
            />
            <p
                v-if="form.errors.reply_template"
                class="text-destructive text-xs"
            >
                {{ form.errors.reply_template }}
            </p>
        </div>

        <Button type="submit" class="mt-5" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Save
        </Button>
    </form>
</template>
