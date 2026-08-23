<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/*
 * O mark maxdraw (`.spec/init/design/logo/maxdraw-mark.svg`) em versão
 * monocromática: traço e retângulos saem de `currentColor`, então os quatro
 * consumidores continuam pintando o ícone pela cor do texto (`fill-current` +
 * `text-*`) e nenhum hexadecimal precisa entrar em quem o consome — é o que
 * deixa a `BoardTopBar` usá-lo sem violar a proibição de cor literal.
 */

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M9 9h14M9 9v14M9 23h14M23 9v14"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            opacity="0.55"
        />
        <rect x="3" y="3" width="12" height="12" rx="3" fill="currentColor" />
        <rect
            x="17"
            y="17"
            width="12"
            height="12"
            rx="3"
            fill="currentColor"
            opacity="0.7"
        />
    </svg>
</template>
