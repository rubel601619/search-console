<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { SearchConsoleProperty } from '@/types/search-console';

const props = defineProps<{
    properties: SearchConsoleProperty[];
    modelValue: string;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const selected = computed({
    get: () => props.modelValue,
    set: (value: string | undefined) => emit('update:modelValue', value ?? ''),
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Globe class="size-4 shrink-0 text-muted-foreground" />
        <Select v-model="selected" :disabled="loading || properties.length === 0">
            <SelectTrigger class="w-full sm:w-80" aria-label="Search Console property">
                <SelectValue placeholder="Select a property" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="property in properties"
                    :key="property.url"
                    :value="property.url"
                >
                    {{ property.url }}
                </SelectItem>
            </SelectContent>
        </Select>
        <Spinner v-if="loading" class="size-4 text-muted-foreground" />
    </div>
</template>
