<script setup lang="ts">
import { ref, watch } from 'vue';
import { fieldValueOf, keepsDraft } from '@/prancheta/estimate';

const props = defineProps<{
    name: string;
    label: string;
    modelValue: number;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: number] }>();

const draft = ref(String(props.modelValue));

/**
 * O texto do campo só é reescrito quando deixa de representar o valor gravado.
 * Recalcular a cada tecla não toca no campo em edição, e é isso que preserva o
 * foco e a posição do cursor — "1." continua sendo "1." enquanto a saída já
 * conta com 1 (US-7.1).
 */
watch(
    () => props.modelValue,
    (value) => {
        if (!keepsDraft(draft.value, value)) {
            draft.value = String(value);
        }
    },
);

function onInput(event: Event): void {
    draft.value = (event.target as HTMLInputElement).value;

    emit('update:modelValue', fieldValueOf(draft.value));
}
</script>

<template>
    <div
        data-testid="est-field"
        :data-field="name"
        class="flex items-center gap-[9px]"
    >
        <label
            :for="`f-${name}`"
            class="flex-1 text-[12.5px] leading-[1.3] text-sd-ink-2"
        >
            {{ label }}
        </label>
        <input
            :id="`f-${name}`"
            :data-testid="`est-input-${name}`"
            type="number"
            min="0"
            step="any"
            inputmode="decimal"
            :value="draft"
            class="h-7 w-24 rounded-md border border-sd-line-2 bg-sd-panel-2 px-2 text-right font-mono text-[12px] text-sd-ink tabular-nums focus:border-sd-accent focus:bg-sd-panel focus:shadow-[0_0_0_3px_var(--accent-soft)] focus:outline-none"
            @input="onInput"
        />
    </div>
</template>
