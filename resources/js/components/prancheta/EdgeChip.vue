<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { labelRoomLeft } from '@/canvas/labels';
import { SEQ_PILL_HEIGHT, SEQ_PILL_PADDING, seqPillWidth } from '@/canvas/svg';

const props = withDefaults(
    defineProps<{
        edgeId: string;
        badge: string;
        label: string;
        bare: boolean;
        color: string;
        x: number;
        y: number;
        order?: number | null;
    }>(),
    { order: null },
);

const emit = defineEmits<{
    rename: [label: string];
}>();

const labelEl = ref<HTMLElement | null>(null);
const editing = ref(false);

/**
 * O chip existe para toda seta viva, mas só ganha moldura quando tem o que
 * emoldurar: sem selo e sem rótulo ele fica sem borda e sem fundo (US-4.2).
 */
const framed = computed(() => !props.bare || editing.value);

const style = computed(() => ({
    '--ec': props.color,
    left: `${props.x.toFixed(1)}px`,
    top: `${props.y.toFixed(1)}px`,
}));

/**
 * O pill do número: fundo cheio na cor da seta e o número na cor do papel. A
 * geometria vem das mesmas constantes do exportador — a tela e o arquivo
 * desenham o mesmo selo, dígito a dígito (UI-01).
 */
const pillStyle = computed(() => ({
    background: 'var(--ec)',
    color: 'var(--paper)',
    minWidth: `${seqPillWidth(props.order ?? 1)}px`,
    height: `${SEQ_PILL_HEIGHT}px`,
    paddingInline: `${SEQ_PILL_PADDING}px`,
}));

/**
 * O rótulo é editado no próprio chip, como o do bloco: Enter confirma, Esc
 * devolve o texto anterior e o excedente de 60 caracteres é barrado antes de
 * entrar no campo.
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

    element.textContent = props.label;
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
        data-testid="edge-chip"
        :data-edge-id="edgeId"
        :data-bare="bare"
        :class="[
            'absolute flex max-w-[210px] -translate-x-1/2 -translate-y-1/2 items-center gap-[5px] rounded font-mono text-[10px] whitespace-nowrap text-sd-ink-2',
            framed
                ? 'border border-sd-line bg-sd-paper px-1.5 py-0.5'
                : 'border-none bg-transparent p-0',
        ]"
        :style="style"
        @dblclick.stop="beginEdit"
    >
        <b
            v-if="order !== null"
            data-testid="edge-chip-seq"
            class="grid flex-none place-items-center rounded-full text-[9px] leading-none font-semibold tabular-nums"
            :style="pillStyle"
            v-text="order"
        ></b>

        <b
            v-if="badge"
            data-testid="edge-chip-badge"
            class="font-semibold tracking-[0.02em]"
            :style="{ color: 'var(--ec)' }"
            v-text="badge"
        ></b>

        <span
            v-if="badge && (label || editing)"
            data-testid="edge-chip-separator"
            class="text-sd-ink-3"
            aria-hidden="true"
            >·</span
        >

        <span
            v-show="label || editing"
            ref="labelEl"
            data-testid="edge-chip-label"
            :contenteditable="editing"
            :class="[
                'cursor-text overflow-hidden text-ellipsis',
                editing
                    ? 'min-w-[14px] rounded-[3px] bg-sd-accent-soft px-[3px] outline-none'
                    : 'max-w-[150px]',
            ]"
            @beforeinput="onBeforeInput"
            @keydown="onLabelKeydown"
            @blur="finishEdit(true)"
            v-text="label"
        ></span>
    </div>
</template>
