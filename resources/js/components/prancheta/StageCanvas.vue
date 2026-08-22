<script setup lang="ts">
import { computed } from 'vue';
import LegendPanel from '@/components/prancheta/LegendPanel.vue';
import ZoomBar from '@/components/prancheta/ZoomBar.vue';

const props = withDefaults(
    defineProps<{
        empty?: boolean;
        scale?: number;
        offsetX?: number;
        offsetY?: number;
        legendVisible?: boolean;
    }>(),
    { empty: true, scale: 1, offsetX: 0, offsetY: 0, legendVisible: false },
);

defineEmits<{
    'zoom-in': [];
    'zoom-out': [];
    fit: [];
    'cycle-sequence': [];
}>();

const gridStyle = computed(() => ({
    backgroundSize: `${26 * props.scale}px ${26 * props.scale}px`,
    backgroundPosition: `${props.offsetX}px ${props.offsetY}px`,
}));

const worldStyle = computed(() => ({
    transform: `translate(${props.offsetX}px, ${props.offsetY}px) scale(${props.scale})`,
}));
</script>

<template>
    <main
        data-testid="stage"
        class="relative min-w-0 touch-none overflow-hidden bg-sd-paper"
    >
        <div
            data-testid="grid"
            class="sd-grid-dots pointer-events-none absolute inset-0"
            :style="gridStyle"
            aria-hidden="true"
        ></div>

        <div
            data-testid="world"
            class="absolute top-0 left-0 size-0 origin-top-left"
            :style="worldStyle"
        >
            <svg
                data-testid="wires"
                class="pointer-events-none absolute top-0 left-0 size-px overflow-visible"
                aria-hidden="true"
            >
                <slot name="wires" />
            </svg>
            <div data-testid="nodes-layer"><slot name="nodes" /></div>
            <div data-testid="labels-layer"><slot name="labels" /></div>
        </div>

        <div
            v-if="empty"
            data-testid="empty-state"
            class="pointer-events-none absolute inset-0 grid place-items-center p-10"
        >
            <div class="max-w-[330px] text-center text-sd-ink-3">
                <b class="mb-1.5 block text-[15px] font-semibold text-sd-ink-2"
                    >Canvas vazio</b
                >
                <p class="m-0 text-[13px] leading-[1.6]">
                    Escolha um componente na barra da esquerda para começar.
                    Depois arraste de uma bolinha até outro bloco para criar a
                    seta e escolha o tipo dela.
                    <kbd
                        class="rounded border border-b-2 border-sd-line bg-sd-panel-2 px-[5px] py-px font-mono text-[11px] text-sd-ink-2"
                        >Del</kbd
                    >
                    apaga,
                    <kbd
                        class="rounded border border-b-2 border-sd-line bg-sd-panel-2 px-[5px] py-px font-mono text-[11px] text-sd-ink-2"
                        >duplo clique</kbd
                    >
                    renomeia.
                </p>
            </div>
        </div>

        <ZoomBar
            :scale="scale"
            @zoom-in="$emit('zoom-in')"
            @zoom-out="$emit('zoom-out')"
            @fit="$emit('fit')"
            @cycle-sequence="$emit('cycle-sequence')"
        />

        <LegendPanel :visible="legendVisible">
            <slot name="legend" />
        </LegendPanel>
    </main>
</template>
