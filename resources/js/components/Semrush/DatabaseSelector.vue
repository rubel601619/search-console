<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Device } from '@/types/semrush';

const props = defineProps<{
    databases: string[];
    database: string;
    device: Device;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:database': [value: string];
    'update:device': [value: Device];
}>();

const selected = computed({
    get: () => props.database,
    set: (value: string | undefined) => emit('update:database', value ?? 'us'),
});

const deviceOptions: { value: Device; label: string }[] = [
    { value: 'desktop', label: 'Desktop' },
    { value: 'mobile', label: 'Mobile' },
];
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2">
            <Globe class="size-4 shrink-0 text-muted-foreground" />
            <Select v-model="selected" :disabled="disabled">
                <SelectTrigger class="w-32" aria-label="Database">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="code in databases"
                        :key="code"
                        :value="code"
                    >
                        {{ code.toUpperCase() }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex items-center gap-1 rounded-lg bg-muted p-1">
            <Button
                v-for="option in deviceOptions"
                :key="option.value"
                :variant="device === option.value ? 'default' : 'ghost'"
                size="sm"
                class="h-7 px-3"
                :disabled="disabled"
                @click="emit('update:device', option.value)"
            >
                {{ option.label }}
            </Button>
        </div>
    </div>
</template>
