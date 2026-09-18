<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="outline"
    size="icon"
    :class="cn('h-8 w-8', props.class)"
    title="Open menu"
    @click="toggleSidebar"
  >
    <Menu />
    <span class="sr-only">Open menu</span>
  </Button>
</template>
