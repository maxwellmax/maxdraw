<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import type { LinkType } from '@/canvas/links';
import type { SequenceNumber } from '@/canvas/sequence';
import type { EdgeFlag, Point, Size } from '@/canvas/types';
import { floatBarSpot } from '@/canvas/view';
import LinkKindMenu from '@/components/prancheta/LinkKindMenu.vue';

const props = withDefaults(
    defineProps<{
        edgeId: string;
        anchor: Point;
        stage: Size;
        types: LinkType[];
        kind: string | null;
        badge: string;
        dashed: boolean;
        bidir: boolean;
        seq?: SequenceNumber | null;
    }>(),
    { seq: null },
);

const emit = defineEmits<{
    'pick-kind': [kind: string | null];
    'edit-label': [];
    toggle: [flag: EdgeFlag];
    reverse: [];
    'move-seq': [direction: number];
    remove: [];
}>();

const root = ref<HTMLElement | null>(null);
const bar = ref<Size>({ width: 0, height: 0 });
const menuOpen = ref(false);

useResizeObserver(root, ([entry]) => {
    bar.value = {
        width: entry.contentRect.width,
        height: entry.contentRect.height,
    };
});

/** Trocar de seta fecha o menu: ele pertence à seta que o abriu. */
watch(
    () => props.edgeId,
    () => (menuOpen.value = false),
);

const style = computed(() => {
    const [left, top] = floatBarSpot(props.anchor, props.stage, bar.value);

    return { left: `${left}px`, top: `${top}px` };
});

function pickKind(kind: string | null): void {
    menuOpen.value = false;
    emit('pick-kind', kind);
}
</script>

<template>
    <div
        ref="root"
        data-testid="floatbar"
        :data-edge-id="edgeId"
        class="absolute z-20 flex items-center gap-0.5 rounded-lg border border-sd-line-2 bg-sd-panel p-[3px] shadow-sd-2"
        :style="style"
        @pointerdown.stop
    >
        <button
            type="button"
            data-testid="edge-kind"
            title="Tipo da ligação"
            :class="[
                'inline-flex h-[26px] min-w-[26px] cursor-pointer items-center gap-[5px] rounded-[5px] px-[7px] text-[12px] hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5',
                kind ? 'bg-sd-accent-soft text-sd-accent' : 'text-sd-ink-2',
            ]"
            @click="menuOpen = !menuOpen"
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
                <path d="M4 8.5h13M13.6 5.2 16.9 8.5l-3.3 3.3" />
                <path d="M20 15.5H7M10.4 12.2 7.1 15.5l3.3 3.3" />
            </svg>
            {{ badge || 'Tipo' }}
        </button>

        <button
            type="button"
            data-testid="edge-label"
            title="Rótulo da ligação"
            class="inline-flex h-[26px] min-w-[26px] cursor-pointer items-center gap-[5px] rounded-[5px] px-[7px] text-[12px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
            @click="emit('edit-label')"
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
                <path d="M4 7h16M4 12h10M4 17h13" />
            </svg>
            Rótulo
        </button>

        <span class="mx-[3px] h-4 w-px bg-sd-line"></span>

        <button
            type="button"
            data-testid="edge-dashed"
            title="Tracejado"
            :class="[
                'inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5',
                dashed ? 'bg-sd-accent-soft text-sd-accent' : 'text-sd-ink-2',
            ]"
            @click="emit('toggle', 'dashed')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M3 12h3M9.5 12h5M18 12h3" />
            </svg>
        </button>

        <button
            type="button"
            data-testid="edge-bidir"
            title="Mão dupla"
            :class="[
                'inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5',
                bidir ? 'bg-sd-accent-soft text-sd-accent' : 'text-sd-ink-2',
            ]"
            @click="emit('toggle', 'bidir')"
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
                <path d="M7 8 4 11l3 3M4 11h16M17 16l3-3-3-3" />
            </svg>
        </button>

        <button
            type="button"
            data-testid="edge-reverse"
            title="Inverter o sentido"
            class="inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
            @click="emit('reverse')"
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
                <path d="M4 8h13l-3-3M20 16H7l3 3" />
            </svg>
        </button>

        <template v-if="seq">
            <span class="mx-[3px] h-4 w-px bg-sd-line"></span>

            <button
                type="button"
                data-testid="edge-seq-back"
                title="Antes na sequência de saída"
                class="inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink disabled:cursor-default disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-sd-ink-2 [&_svg]:size-3.5"
                :disabled="seq.index <= 1"
                @click="emit('move-seq', -1)"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.1"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M14.5 5.5 8 12l6.5 6.5" />
                </svg>
            </button>

            <span
                data-testid="edge-seq-position"
                title="Ordem em que o bloco dispara esta saída"
                class="min-w-[26px] px-px text-center font-mono text-[11px] text-sd-ink-3 tabular-nums"
                >{{ seq.index }}/{{ seq.total }}</span
            >

            <button
                type="button"
                data-testid="edge-seq-forward"
                title="Depois na sequência de saída"
                class="inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink disabled:cursor-default disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-sd-ink-2 [&_svg]:size-3.5"
                :disabled="seq.index >= seq.total"
                @click="emit('move-seq', 1)"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.1"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M9.5 5.5 16 12l-6.5 6.5" />
                </svg>
            </button>
        </template>

        <span class="mx-[3px] h-4 w-px bg-sd-line"></span>

        <button
            type="button"
            data-testid="edge-delete"
            title="Apagar a ligação"
            class="inline-flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[5px] px-[7px] text-sd-ink-2 hover:bg-[color-mix(in_srgb,var(--crit)_14%,transparent)] hover:text-sd-crit [&_svg]:size-3.5"
            @click="emit('remove')"
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
                <path d="M5 7h14M10 7V5h4v2M6.5 7l1 12.5h9l1-12.5" />
            </svg>
        </button>

        <LinkKindMenu
            v-if="menuOpen"
            :types="types"
            :kind="kind"
            @pick="pickKind"
        />
    </div>
</template>
