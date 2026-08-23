<script setup lang="ts">
import DrillTabs from '@/components/prancheta/DrillTabs.vue';
import type { DrillTabId } from '@/prancheta/tabs';
import { DRILL_TABS } from '@/prancheta/tabs';

defineProps<{ modelValue: DrillTabId }>();

defineEmits<{ 'update:modelValue': [value: DrillTabId] }>();
</script>

<template>
    <aside
        data-testid="drill"
        class="flex flex-col overflow-hidden border-l border-sd-line bg-sd-panel"
    >
        <slot name="clock" />

        <DrillTabs
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <!-- Os painéis ficam montados o tempo todo: trocar de aba não pode
             perder o texto das notas nem os campos da calculadora. -->
        <div
            v-for="tab in DRILL_TABS"
            v-show="modelValue === tab.id"
            :key="tab.id"
            :id="`pane-${tab.id}`"
            :data-testid="`pane-${tab.id}`"
            role="tabpanel"
            :aria-labelledby="`tab-${tab.id}`"
            class="flex-1 overflow-y-auto overscroll-contain px-4 pt-3.5 pb-7"
        >
            <slot v-if="tab.id === 'roteiro'" name="roteiro" />
            <slot v-else-if="tab.id === 'enunciado'" name="enunciado" />
            <slot v-else-if="tab.id === 'calc'" name="calc" />
            <slot v-else name="notas" />
        </div>
    </aside>
</template>
