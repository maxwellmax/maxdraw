<script setup lang="ts">
import { computed } from 'vue';

export type ChipState = 'dirty' | 'saving' | 'saved';

const props = withDefaults(
    defineProps<{
        state: ChipState;
        label?: string;
    }>(),
    { label: undefined },
);

const DOTS: Record<ChipState, string> = {
    dirty: 'bg-sd-warn',
    saving: 'bg-sd-ink-3',
    saved: 'bg-sd-ok',
};

const LABELS: Record<ChipState, string> = {
    dirty: 'não salvo',
    saving: 'salvando…',
    saved: 'salvo',
};

const text = computed(() => props.label ?? LABELS[props.state]);
</script>

<template>
    <div
        class="flex items-center gap-[5px] font-mono text-[11px] whitespace-nowrap text-sd-ink-3"
        :data-state="state"
    >
        <i
            class="block size-1.5 rounded-full"
            :class="DOTS[state]"
            aria-hidden="true"
        />
        <span>{{ text }}</span>
    </div>
</template>
