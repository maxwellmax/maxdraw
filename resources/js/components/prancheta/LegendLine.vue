<script setup lang="ts">
import { computed } from 'vue';
import { lineSample, SAMPLE_VIEWBOX } from '@/canvas/legend';

const props = withDefaults(
    defineProps<{ dash?: string | null; bidir?: boolean }>(),
    { dash: null, bidir: false },
);

const sample = computed(() => lineSample(props.bidir));
</script>

<template>
    <svg
        data-testid="legend-line"
        class="h-3 w-10 flex-none text-sd-ink-3"
        :viewBox="SAMPLE_VIEWBOX"
        aria-hidden="true"
    >
        <path
            :d="sample.line"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            :stroke-dasharray="dash ?? undefined"
        />
        <path :d="sample.head" fill="currentColor" stroke="none" />
        <path
            v-if="sample.tail"
            :d="sample.tail"
            fill="currentColor"
            stroke="none"
        />
    </svg>
</template>
