<script setup lang="ts">
/*
 * O lockup maxdraw em duas variantes servidas direto de `public/brand/`. Quem
 * escolhe entre elas é `resources/css/brand.css`: a decisão é do CSS, nunca
 * daqui, então a variante certa já sai do SSR e não pisca. O hexadecimal da
 * marca fica dentro dos dois SVGs, fora das telas que o consomem.
 */

const alt = 'maxdraw — treino de system design';
</script>

<template>
    <span class="flex justify-center">
        <img
            src="/brand/maxdraw-lockup-dark.svg"
            :alt="alt"
            width="320"
            height="80"
            class="brand-lockup__dark h-16 w-auto"
        />
        <img
            src="/brand/maxdraw-lockup-light.svg"
            :alt="alt"
            width="320"
            height="80"
            class="brand-lockup__light h-16 w-auto"
        />
    </span>
</template>
