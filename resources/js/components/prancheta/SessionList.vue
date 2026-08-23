<script setup lang="ts">
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
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

defineEmits<{
    open: [sessionId: number];
    remove: [sessionId: number];
}>();
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
                <b
                    data-testid="session-row-name"
                    class="mb-0.5 flex items-center gap-1.5 text-[13.5px] font-semibold tracking-[-0.01em] text-sd-ink"
                >
                    {{ row.problemName }}

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
                    {{ row.date }} · {{ row.durationLabel }} ·
                    {{ row.elapsedLabel }} · {{ row.blockCount }} blocos
                </span>
            </div>

            <PranchetaButton
                data-testid="session-open"
                @click="$emit('open', row.id)"
            >
                Abrir
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
