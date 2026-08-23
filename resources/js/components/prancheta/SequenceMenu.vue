<script setup lang="ts">
import type { SequenceMenuItem } from '@/canvas/sequence';
import type { SequenceMode } from '@/canvas/types';

defineProps<{
    options: SequenceMenuItem[];
    mode: SequenceMode;
}>();

defineEmits<{
    pick: [mode: SequenceMode];
}>();
</script>

<template>
    <div
        data-testid="sequence-menu"
        class="absolute bottom-[calc(100%+6px)] left-0 z-[25] min-w-[186px] rounded-[9px] border border-sd-line-2 bg-sd-panel p-1 shadow-sd-2"
        @pointerdown.stop
    >
        <div
            data-testid="sequence-menu-head"
            class="px-2 pt-[5px] pb-1 font-mono text-[9.5px] font-semibold tracking-[0.09em] text-sd-ink-3 uppercase"
        >
            Numeração das setas
        </div>

        <button
            v-for="option in options"
            :key="option.mode"
            type="button"
            data-testid="sequence-option"
            :data-mode="option.mode"
            :class="[
                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] hover:bg-sd-panel-2 hover:text-sd-ink',
                mode === option.mode
                    ? 'font-semibold text-sd-accent'
                    : 'text-sd-ink-2',
            ]"
            @click="$emit('pick', option.mode)"
        >
            {{ option.name }}
        </button>
    </div>
</template>
