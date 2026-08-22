<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { computed, nextTick, ref } from 'vue';
import { labelRoomLeft } from '@/canvas/labels';
import type { Node } from '@/canvas/types';
import CanvasIcon from '@/components/prancheta/CanvasIcon.vue';

const props = defineProps<{
    node: Node;
    color: string;
    iconKey: string;
    typeName: string;
    selected: boolean;
}>();

const emit = defineEmits<{
    rename: [label: string];
    measure: [height: number];
}>();

const root = ref<HTMLElement | null>(null);
const labelEl = ref<HTMLElement | null>(null);
const editing = ref(false);

useResizeObserver(root, ([entry]) =>
    emit('measure', (entry.target as HTMLElement).offsetHeight),
);

const style = computed(() => ({
    '--nc': props.color,
    left: `${props.node.x}px`,
    top: `${props.node.y}px`,
    boxShadow: props.selected
        ? '0 0 0 2px color-mix(in srgb, var(--nc) 34%, transparent), var(--shadow-2)'
        : undefined,
    borderColor: props.selected ? 'var(--nc)' : undefined,
}));

/**
 * O rótulo é editado no próprio bloco. Enter confirma, Esc devolve o texto
 * anterior sem gravar, e o excedente de 60 caracteres é barrado antes de entrar
 * no campo — nunca truncado depois (US-3.2).
 */
function beginEdit(): void {
    if (editing.value) {
        return;
    }

    editing.value = true;

    nextTick(() => {
        const element = labelEl.value;

        if (!element) {
            return;
        }

        element.focus();

        const range = document.createRange();

        range.selectNodeContents(element);
        getSelection()?.removeAllRanges();
        getSelection()?.addRange(range);
    });
}

function finishEdit(commit: boolean): void {
    const element = labelEl.value;

    if (!editing.value || !element) {
        return;
    }

    editing.value = false;

    const typed = element.textContent ?? '';

    element.textContent = props.node.label;
    element.blur();

    if (commit) {
        emit('rename', typed);
    }
}

function onLabelKeydown(event: KeyboardEvent): void {
    event.stopPropagation();

    if (event.key === 'Enter') {
        event.preventDefault();
        finishEdit(true);
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        finishEdit(false);
    }
}

function onBeforeInput(event: InputEvent): void {
    const incoming =
        event.data ?? event.dataTransfer?.getData('text/plain') ?? '';

    if (incoming === '') {
        return;
    }

    const selection = getSelection();
    const replacing =
        selection?.isCollapsed === false ? selection.toString().length : 0;

    if (
        labelRoomLeft(labelEl.value?.textContent ?? '', replacing) <
        incoming.length
    ) {
        event.preventDefault();
    }
}

defineExpose({ beginEdit });
</script>

<template>
    <div
        ref="root"
        data-testid="node"
        :data-node-id="node.id"
        :data-selected="selected"
        :class="[
            'group absolute flex min-h-[86px] w-[132px] cursor-grab flex-col items-center gap-0.5 rounded-[10px] border border-sd-line-2 bg-sd-panel px-2 pt-[11px] pb-[9px] shadow-sd-1 select-none hover:shadow-sd-2',
            { 'shadow-sd-2': selected },
        ]"
        :style="style"
        @dblclick.stop="beginEdit"
    >
        <div
            data-testid="node-icon"
            class="grid size-[34px] place-items-center rounded-lg border [&_svg]:size-5"
            :style="{
                color: 'var(--nc)',
                background: 'color-mix(in srgb, var(--nc) 11%, transparent)',
                borderColor: 'color-mix(in srgb, var(--nc) 26%, transparent)',
            }"
        >
            <CanvasIcon :icon-key="iconKey" />
        </div>

        <div
            ref="labelEl"
            data-testid="node-label"
            :contenteditable="editing"
            :class="[
                'mt-[3px] max-w-full cursor-text text-center font-sd-ui text-[12.5px] leading-[1.25] font-semibold tracking-[-0.005em] break-words',
                {
                    'rounded-[3px] bg-sd-accent-soft px-[3px] outline-none':
                        editing,
                },
            ]"
            @beforeinput="onBeforeInput"
            @keydown="onLabelKeydown"
            @blur="finishEdit(true)"
            v-text="node.label"
        ></div>

        <div
            data-testid="node-type"
            class="text-center font-mono text-[8.5px] tracking-[0.07em] uppercase opacity-85"
            :style="{ color: 'var(--nc)' }"
            v-text="typeName"
        ></div>

        <span
            v-for="handle in ['t', 'r', 'b', 'l']"
            :key="handle"
            data-testid="node-handle"
            :data-handle="handle"
            :class="[
                'absolute z-[5] size-[11px] cursor-crosshair rounded-full border-[1.8px] bg-sd-panel opacity-0 transition-opacity group-hover:opacity-100 hover:scale-[1.35]',
                {
                    'top-[-6px] left-1/2 -ml-[5.5px]': handle === 't',
                    'top-1/2 right-[-6px] -mt-[5.5px]': handle === 'r',
                    'bottom-[-6px] left-1/2 -ml-[5.5px]': handle === 'b',
                    'top-1/2 left-[-6px] -mt-[5.5px]': handle === 'l',
                    'opacity-100': selected,
                },
            ]"
            :style="{ borderColor: 'var(--nc)' }"
        ></span>
    </div>
</template>
