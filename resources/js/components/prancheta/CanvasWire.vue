<script setup lang="ts">
import type { EdgeGeometry } from '@/canvas/types';

withDefaults(
    defineProps<{
        edgeId: string;
        geometry: EdgeGeometry;
        headPath: string;
        color: string;
        selected: boolean;
        dash?: string | null;
        tailPath?: string | null;
    }>(),
    { dash: null, tailPath: null },
);
</script>

<template>
    <g data-testid="wire" :data-edge-id="edgeId">
        <path
            v-if="selected"
            :d="geometry.d"
            fill="none"
            stroke-width="9"
            stroke-opacity="0.22"
            stroke-linecap="round"
            :stroke="color"
        />
        <path
            data-testid="wire-line"
            :d="geometry.d"
            fill="none"
            :stroke-width="selected ? 2.6 : 1.8"
            stroke-linecap="round"
            :stroke="color"
            :stroke-dasharray="dash ?? undefined"
            class="pointer-events-none"
        />
        <path
            data-testid="wire-head"
            :d="headPath"
            :fill="color"
            stroke="none"
        />
        <path
            v-if="tailPath"
            data-testid="wire-tail"
            :d="tailPath"
            :fill="color"
            stroke="none"
        />
        <path
            :d="geometry.d"
            fill="none"
            stroke="transparent"
            stroke-width="16"
            class="cursor-pointer"
            style="pointer-events: stroke"
        />
    </g>
</template>
