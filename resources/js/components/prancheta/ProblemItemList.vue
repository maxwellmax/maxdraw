<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        items: readonly string[];
        variant?: 'prose' | 'scale';
    }>(),
    { variant: 'prose' },
);

/**
 * A escala é número, então sai em monoespaçada e com o marcador na cor de
 * Dados — é a única diferença entre as três listas do enunciado.
 */
const VARIANTS = {
    prose: 'before:bg-sd-line-2',
    scale: 'font-sd-mono text-[11.5px] before:bg-sd-c-data',
} as const;

const itemClasses = computed(() => VARIANTS[props.variant]);
</script>

<template>
    <ul
        data-testid="problem-items"
        :data-variant="variant"
        class="m-0 flex list-none flex-col gap-[5px] p-0"
    >
        <li
            v-for="(item, index) in items"
            :key="index"
            data-testid="problem-item"
            class="relative pl-[15px] text-[13px] leading-[1.45] text-sd-ink-2 before:absolute before:top-2 before:left-[3px] before:size-1 before:rounded-[1px] before:content-['']"
            :class="itemClasses"
        >
            {{ item }}
        </li>
    </ul>
</template>
