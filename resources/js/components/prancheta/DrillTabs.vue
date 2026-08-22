<script setup lang="ts">
import { ref } from 'vue';
import type { DrillTabId } from '@/prancheta/tabs';
import { DRILL_TABS } from '@/prancheta/tabs';

const props = defineProps<{ modelValue: DrillTabId }>();

const emit = defineEmits<{ 'update:modelValue': [value: DrillTabId] }>();

const buttons = ref<HTMLButtonElement[]>([]);

function select(index: number): void {
    const tab = DRILL_TABS[index];

    emit('update:modelValue', tab.id);
    buttons.value[index]?.focus();
}

function nextTabIndex(
    key: string,
    current: number,
    last: number,
): number | null {
    switch (key) {
        case 'ArrowRight':
            return current === last ? 0 : current + 1;
        case 'ArrowLeft':
            return current === 0 ? last : current - 1;
        case 'Home':
            return 0;
        case 'End':
            return last;
        default:
            return null;
    }
}

function onKeydown(event: KeyboardEvent): void {
    const current = DRILL_TABS.findIndex((tab) => tab.id === props.modelValue);
    const target = nextTabIndex(event.key, current, DRILL_TABS.length - 1);

    if (target === null) {
        return;
    }

    event.preventDefault();
    select(target);
}
</script>

<template>
    <div
        data-testid="tabs"
        role="tablist"
        aria-label="Painel de treino"
        class="flex border-b border-sd-line bg-sd-panel px-2.5"
        @keydown="onKeydown"
    >
        <button
            v-for="(tab, index) in DRILL_TABS"
            :key="tab.id"
            ref="buttons"
            type="button"
            role="tab"
            :id="`tab-${tab.id}`"
            :data-testid="`tab-${tab.id}`"
            :aria-selected="modelValue === tab.id"
            :aria-controls="`pane-${tab.id}`"
            :tabindex="modelValue === tab.id ? 0 : -1"
            class="-mb-px cursor-pointer border-b-2 border-transparent px-[9px] pt-[9px] pb-2 text-[12.5px] font-medium text-sd-ink-3 hover:text-sd-ink-2 aria-selected:border-b-sd-accent aria-selected:font-semibold aria-selected:text-sd-ink"
            @click="select(index)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
