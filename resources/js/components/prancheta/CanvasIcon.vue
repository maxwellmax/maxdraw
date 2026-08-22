<script setup lang="ts">
import { computed } from 'vue';
import { CANVAS_ICONS, hasCanvasIcon } from '@/canvas/icons';

const props = defineProps<{ iconKey: string }>();

const shapes = computed(() =>
    hasCanvasIcon(props.iconKey) ? CANVAS_ICONS[props.iconKey] : '',
);
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-html="shapes"
    ></svg>
</template>
