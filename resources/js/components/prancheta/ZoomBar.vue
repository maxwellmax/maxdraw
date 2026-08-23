<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { SequenceMenuItem } from '@/canvas/sequence';
import type { SequenceMode } from '@/canvas/types';
import SequenceMenu from '@/components/prancheta/SequenceMenu.vue';

const props = withDefaults(
    defineProps<{
        scale?: number;
        sequenceMode?: SequenceMode;
        sequenceModes?: SequenceMenuItem[];
    }>(),
    { scale: 1, sequenceMode: 'out', sequenceModes: () => [] },
);

const emit = defineEmits<{
    'zoom-in': [];
    'zoom-out': [];
    fit: [];
    'pick-sequence': [mode: SequenceMode];
}>();

const percent = computed(() => `${Math.round(props.scale * 100)}%`);

const menuOpen = ref(false);

const seqButton = ref<HTMLElement | null>(null);

/**
 * A barra de zoom nunca sai da tela, então o menu dela precisa se fechar
 * sozinho: sem isso ele ficaria aberto sobre o palco depois de o usuário
 * desistir. O menu de tipo não precisa — ele some junto com a seta selecionada.
 */
onClickOutside(seqButton, () => (menuOpen.value = false));

/** O botão fica aceso enquanto houver número desenhado (US-4.3). */
const numbering = computed(() => props.sequenceMode !== 'off');

function pickSequence(mode: SequenceMode): void {
    menuOpen.value = false;
    emit('pick-sequence', mode);
}
</script>

<template>
    <div
        data-testid="zoombar"
        class="absolute bottom-3 left-3 z-10 flex items-center gap-0.5 rounded-lg border border-sd-line bg-sd-panel p-[3px] shadow-sd-1"
    >
        <button
            type="button"
            data-testid="zoom-out"
            title="Diminuir zoom"
            class="grid size-[26px] cursor-pointer place-items-center rounded-[5px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
            @click="$emit('zoom-out')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M6 12h12" />
            </svg>
        </button>
        <div
            data-testid="zoom-value"
            class="min-w-[44px] px-1.5 text-center font-mono text-[11px] text-sd-ink-3 tabular-nums"
        >
            {{ percent }}
        </div>
        <button
            type="button"
            data-testid="zoom-in"
            title="Aumentar zoom"
            class="grid size-[26px] cursor-pointer place-items-center rounded-[5px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
            @click="$emit('zoom-in')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M12 6v12M6 12h12" />
            </svg>
        </button>
        <button
            type="button"
            data-testid="zoom-fit"
            title="Enquadrar tudo"
            class="grid size-[26px] cursor-pointer place-items-center rounded-[5px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
            @click="$emit('fit')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path
                    d="M4 9V5.5A1.5 1.5 0 0 1 5.5 4H9M15 4h3.5A1.5 1.5 0 0 1 20 5.5V9M20 15v3.5a1.5 1.5 0 0 1-1.5 1.5H15M9 20H5.5A1.5 1.5 0 0 1 4 18.5V15"
                />
            </svg>
        </button>
        <span class="mx-[3px] h-4 w-px bg-sd-line"></span>
        <div ref="seqButton" class="relative">
            <button
                type="button"
                data-testid="sequence-mode"
                title="Numeração das setas"
                :class="[
                    'grid size-[26px] cursor-pointer place-items-center rounded-[5px] hover:bg-sd-panel-2 hover:text-sd-ink',
                    numbering
                        ? 'bg-sd-accent-soft text-sd-accent'
                        : 'text-sd-ink-2',
                ]"
                @click="menuOpen = !menuOpen"
            >
                <span
                    class="font-mono text-[10px] font-semibold tracking-[-0.02em]"
                    >1&#8594;2</span
                >
            </button>

            <SequenceMenu
                v-if="menuOpen"
                :options="sequenceModes"
                :mode="sequenceMode"
                @pick="pickSequence"
            />
        </div>
    </div>
</template>
