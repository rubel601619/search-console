<script setup lang="ts">
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    domain: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    analyze: [domain: string];
}>();

const draft = ref(props.domain);

function submit(): void {
    const value = draft.value.trim();

    if (value === '') {
        return;
    }

    emit('analyze', value);
}
</script>

<template>
    <form class="flex w-full gap-2 sm:max-w-md" @submit.prevent="submit">
        <div class="relative flex-1">
            <Search
                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="draft"
                type="text"
                inputmode="url"
                placeholder="e.g. example.com"
                class="h-9 pl-8"
                :disabled="disabled"
                aria-label="Domain"
            />
        </div>
        <Button type="submit" :disabled="disabled || draft.trim() === ''">
            Analyze
        </Button>
    </form>
</template>
