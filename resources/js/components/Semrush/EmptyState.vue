<script setup lang="ts">
import { ScanSearch } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
    }>(),
    {
        icon: ScanSearch,
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center"
    >
        <div
            class="flex size-10 items-center justify-center rounded-full bg-muted"
        >
            <component :is="icon" class="size-5 text-muted-foreground" />
        </div>
        <div class="space-y-1">
            <p class="text-sm font-medium">{{ title }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
