<script setup lang="ts">
import { acceptNotes, NOTES_MAX_LENGTH } from '@/prancheta/notes';

defineProps<{ modelValue: string }>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blocked: [];
}>();

/**
 * Passar do limite não trunca em silêncio: o campo volta ao texto aceito e a
 * página avisa pelo toast (US-6.3).
 */
function onInput(event: Event): void {
    const field = event.target as HTMLTextAreaElement;
    const accepted = acceptNotes(field.value);

    if (accepted.blocked) {
        field.value = accepted.notes;
        emit('blocked');
    }

    emit('update:modelValue', accepted.notes);
}
</script>

<template>
    <textarea
        data-testid="notes"
        spellcheck="false"
        :value="modelValue"
        :data-max="NOTES_MAX_LENGTH"
        placeholder="API, modelo de dados, trade-offs, perguntas que você faria…"
        class="min-h-[340px] w-full resize-y rounded-sd border border-sd-line-2 bg-sd-panel-2 px-3 py-[11px] font-mono text-[12.5px] leading-[1.65] text-sd-ink focus:border-sd-accent focus:bg-sd-panel focus:outline-none"
        @input="onInput"
    ></textarea>
</template>
