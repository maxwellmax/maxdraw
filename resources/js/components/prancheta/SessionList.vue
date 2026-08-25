<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';
import { nextTick, ref } from 'vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import PranchetaInput from '@/components/prancheta/PranchetaInput.vue';
import type { SessionRow } from '@/prancheta/sessions';
import {
    CURRENT_BADGE,
    deleteLabel,
    EMPTY_LIST_MESSAGE,
} from '@/prancheta/sessions';

defineProps<{
    rows: readonly SessionRow[];
    armed: number | null;
}>();

const emit = defineEmits<{
    open: [sessionId: number];
    remove: [sessionId: number];
    rename: [sessionId: number, name: string];
}>();

/** A linha cujo nome está aberto para edição, e o texto digitado nela. */
const editingId = ref<number | null>(null);
const draft = ref('');

const renameField = ref<HTMLInputElement | null>(null);

/**
 * O nome é editado na própria linha, como o rótulo do bloco: o campo abre com
 * o nome atual — vazio quando não há nenhum —, Enter e a saída do campo
 * confirmam, e Esc fecha sem gravar (US-11.1). Nada daqui fala com o servidor.
 */
function beginEdit(row: SessionRow): void {
    if (editingId.value === row.id) {
        return;
    }

    editingId.value = row.id;
    draft.value = row.name ?? '';

    nextTick(() => {
        renameField.value?.focus();
        renameField.value?.select();
    });
}

function finishEdit(sessionId: number, commit: boolean): void {
    if (editingId.value !== sessionId) {
        return;
    }

    const typed = draft.value;

    editingId.value = null;
    draft.value = '';

    if (commit) {
        emit('rename', sessionId, typed);
    }
}

function setRenameField(
    instance: Element | ComponentPublicInstance | null,
): void {
    const root = (instance as ComponentPublicInstance | null)?.$el as
        HTMLElement | undefined;

    renameField.value = root?.querySelector('input') ?? null;
}

function onDraftInput(event: Event): void {
    draft.value = (event.target as HTMLInputElement).value;
}
</script>

<template>
    <div data-testid="session-list" class="flex flex-col gap-1.5">
        <p
            v-if="rows.length === 0"
            data-testid="session-empty"
            class="m-0 p-2 text-[12.5px] text-sd-ink-3"
        >
            {{ EMPTY_LIST_MESSAGE }}
        </p>

        <div
            v-for="row in rows"
            :key="row.id"
            data-testid="session-row"
            :data-session-id="row.id"
            :aria-current="row.current"
            class="flex items-center gap-2.5 rounded-[9px] border border-sd-line bg-sd-panel-2 px-[13px] py-[9px] aria-[current=true]:border-sd-accent"
        >
            <div class="min-w-0 flex-1">
                <PranchetaInput
                    v-if="editingId === row.id"
                    :ref="(instance) => setRenameField(instance)"
                    data-testid="session-rename-input"
                    :value="draft"
                    aria-label="Nome da sessão"
                    placeholder="Nome da sessão"
                    @input="onDraftInput"
                    @keydown.enter.prevent="finishEdit(row.id, true)"
                    @keydown.esc.stop.prevent="finishEdit(row.id, false)"
                    @blur="finishEdit(row.id, true)"
                />

                <b
                    v-else
                    data-testid="session-row-name"
                    class="mb-0.5 flex items-center gap-1.5 text-[13.5px] font-semibold tracking-[-0.01em] text-sd-ink"
                >
                    {{ row.title }}

                    <span
                        v-if="row.current"
                        data-testid="session-current-badge"
                        class="rounded-full border border-sd-accent px-1.5 font-sd-mono text-[9.5px] tracking-[0.08em] text-sd-accent uppercase"
                    >
                        {{ CURRENT_BADGE }}
                    </span>
                </b>

                <span
                    data-testid="session-row-meta"
                    class="block text-[11.5px] leading-[1.45] text-sd-ink-3"
                >
                    {{ row.metaLabel }}
                </span>
            </div>

            <PranchetaButton
                data-testid="session-open"
                @click="$emit('open', row.id)"
            >
                Abrir
            </PranchetaButton>

            <PranchetaButton
                data-testid="session-rename"
                title="Renomear esta sessão"
                @click="beginEdit(row)"
            >
                Renomear
            </PranchetaButton>

            <PranchetaButton
                data-testid="session-delete"
                :data-armed="armed === row.id"
                :title="`${deleteLabel(armed, row.id)} esta sessão`"
                @click="$emit('remove', row.id)"
            >
                {{ deleteLabel(armed, row.id) }}
            </PranchetaButton>
        </div>
    </div>
</template>
