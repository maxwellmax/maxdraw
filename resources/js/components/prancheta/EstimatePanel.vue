<script setup lang="ts">
import { computed } from 'vue';
import EstimateField from '@/components/prancheta/EstimateField.vue';
import type {
    EstimateFieldKey,
    EstimateModeOption,
} from '@/prancheta/estimate';
import {
    conclusionFor,
    ESTIMATE_NOTE,
    estimateRows,
    fieldsOf,
} from '@/prancheta/estimate';
import type { SessionEstimate } from '@/prancheta/session';

const props = defineProps<{
    estimate: SessionEstimate;
    modes: readonly EstimateModeOption[];
}>();

defineEmits<{
    'pick-mode': [slug: string];
    'update-field': [key: EstimateFieldKey, value: number];
}>();

/**
 * Trocar de modo troca só os campos próprios: os comuns são os mesmos objetos
 * do catálogo de campos, então os valores atravessam a troca intactos (US-7.2).
 */
const fields = computed(() => fieldsOf(props.estimate.mode));

const rows = computed(() => estimateRows(props.estimate, props.modes));

const conclusion = computed(() => conclusionFor(props.estimate));
</script>

<template>
    <div data-testid="calc">
        <div data-testid="est-modes" class="mb-[13px] flex gap-1">
            <button
                v-for="mode in modes"
                :key="mode.slug"
                type="button"
                :data-testid="`est-mode-${mode.slug}`"
                :aria-pressed="mode.slug === estimate.mode"
                class="h-[29px] flex-1 cursor-pointer rounded-md border border-sd-line text-[12px] text-sd-ink-3 hover:border-sd-line-2 hover:text-sd-ink-2 aria-pressed:border-sd-accent aria-pressed:bg-sd-accent-soft aria-pressed:font-semibold aria-pressed:text-sd-accent"
                @click="$emit('pick-mode', mode.slug)"
            >
                {{ mode.name }}
            </button>
        </div>

        <div data-testid="est-fields" class="flex flex-col gap-[9px]">
            <EstimateField
                v-for="field in fields"
                :key="field.key"
                :name="field.key"
                :label="field.label"
                :model-value="estimate[field.key]"
                @update:model-value="$emit('update-field', field.key, $event)"
            />
        </div>

        <div
            data-testid="est-out"
            class="mt-1.5 overflow-hidden rounded-sd border border-sd-line"
        >
            <div
                v-for="row in rows"
                :key="row.key"
                data-testid="est-row"
                :data-row="row.key"
                :data-hi="row.highlighted"
                class="group flex items-baseline gap-2 border-b border-sd-line px-[11px] py-[7px] last:border-b-0 odd:bg-sd-panel-2"
            >
                <span
                    data-testid="est-row-label"
                    class="flex-1 text-[12px] text-sd-ink-2"
                >
                    {{ row.label }}
                </span>
                <span
                    data-testid="est-row-value"
                    class="font-mono text-[13px] font-medium text-sd-ink tabular-nums group-data-[hi=true]:font-semibold group-data-[hi=true]:text-sd-accent"
                >
                    {{ row.value }}
                </span>
                <span
                    data-testid="est-row-unit"
                    class="w-11 text-left font-mono text-[10px] text-sd-ink-3"
                >
                    {{ row.unit }}
                </span>
            </div>
        </div>

        <p
            data-testid="est-note"
            class="mt-2.5 text-[11.5px] leading-[1.5] text-sd-ink-3"
        >
            <span data-testid="est-note-lead">{{ ESTIMATE_NOTE }}</span>
            <b data-testid="est-conclusion">{{ conclusion }}</b
            >.
        </p>
    </div>
</template>
