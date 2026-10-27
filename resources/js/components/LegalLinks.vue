<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { dataDeletion, privacy, terms } from '@/routes';
</script>

<template>
    <nav
        class="text-muted-foreground flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs"
    >
        <Link :href="privacy()" class="hover:text-foreground">
            Privacy Policy
        </Link>
        <Link :href="terms()" class="hover:text-foreground">
            Terms of Service
        </Link>
        <Link :href="dataDeletion()" class="hover:text-foreground">
            Data deletion
        </Link>
    </nav>
</template>
