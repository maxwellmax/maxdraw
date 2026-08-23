<script setup lang="ts">
import { useLegend } from '@/composables/useLegend';

withDefaults(defineProps<{ visible?: boolean }>(), { visible: false });

/**
 * Recolher é preferência do navegador e mora fora daqui, porque o enquadramento
 * também precisa dela: legenda recolhida não reserva largura (US-5.2).
 */
const { open, toggle } = useLegend();
</script>

<template>
    <section
        v-if="visible"
        data-testid="legend"
        :data-open="open"
        class="absolute right-3 bottom-3 z-10 flex max-h-[calc(100%-24px)] w-[238px] flex-col overflow-hidden rounded-lg border border-sd-line bg-sd-panel shadow-sd-1"
    >
        <button
            type="button"
            data-testid="legend-toggle"
            title="Mostrar / esconder a legenda"
            :aria-expanded="open"
            class="flex w-full flex-none cursor-pointer items-center gap-1.5 px-[9px] py-[7px] font-mono text-[10px] font-semibold tracking-[0.1em] text-sd-ink-3 uppercase hover:bg-sd-panel-2 hover:text-sd-ink-2"
            @click="toggle"
        >
            <span>Legenda</span>
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="ml-auto size-3 transition-transform"
                :class="{ '-rotate-90': !open }"
                aria-hidden="true"
            >
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>
        <div
            v-show="open"
            data-testid="legend-body"
            class="overflow-y-auto overscroll-contain border-t border-sd-line px-2.5 pt-px pb-[9px]"
        >
            <slot />
        </div>
    </section>
</template>
