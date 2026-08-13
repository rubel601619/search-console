<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { DateRange } from '@/types/search-console';

const props = defineProps<{
    modelValue: DateRange;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: DateRange];
}>();

function toISODate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function today(): string {
    return toISODate(new Date());
}

function daysAgo(days: number): string {
    const date = new Date();

    date.setDate(date.getDate() - (days - 1));

    return toISODate(date);
}

const presets = computed(() => [
    { label: '7d', startDate: daysAgo(7), endDate: today() },
    { label: '28d', startDate: daysAgo(28), endDate: today() },
    { label: '90d', startDate: daysAgo(90), endDate: today() },
]);

const activePreset = computed(() =>
    presets.value.find(
        (preset) =>
            preset.startDate === props.modelValue.startDate
            && preset.endDate === props.modelValue.endDate,
    )?.label,
);

function selectPreset(startDate: string, endDate: string): void {
    emit('update:modelValue', { startDate, endDate });
}

function updateDate(key: keyof DateRange, value: string): void {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <Calendar class="size-4 shrink-0 text-muted-foreground" />
            <div class="flex flex-wrap items-center gap-1">
                <Button
                    v-for="preset in presets"
                    :key="preset.label"
                    :variant="activePreset === preset.label ? 'default' : 'outline'"
                    size="sm"
                    :disabled="disabled"
                    @click="selectPreset(preset.startDate, preset.endDate)"
                >
                    {{ preset.label }}
                </Button>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:max-w-sm">
            <div class="space-y-1.5">
                <Label for="search-console-start-date" class="text-xs text-muted-foreground">From</Label>
                <input
                    id="search-console-start-date"
                    type="date"
                    :value="modelValue.startDate"
                    :max="modelValue.endDate"
                    :disabled="disabled"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    @change="updateDate('startDate', ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="search-console-end-date" class="text-xs text-muted-foreground">To</Label>
                <input
                    id="search-console-end-date"
                    type="date"
                    :value="modelValue.endDate"
                    :min="modelValue.startDate"
                    :max="today()"
                    :disabled="disabled"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    @change="updateDate('endDate', ($event.target as HTMLInputElement).value)"
                />
            </div>
        </div>
    </div>
</template>
