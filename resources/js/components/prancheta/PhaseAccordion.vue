<script setup lang="ts">
import ChecklistItem from '@/components/prancheta/ChecklistItem.vue';
import type { PhaseRow } from '@/prancheta/roteiro';

defineProps<{ phases: readonly PhaseRow[] }>();

defineEmits<{ 'toggle-phase': [index: number]; 'toggle-item': [id: number] }>();
</script>

<template>
    <div data-testid="phases" class="flex flex-col gap-0.5">
        <div
            v-for="phase in phases"
            :key="phase.index"
            data-testid="phase"
            :data-state="phase.state"
            class="group relative ml-[5px] border-l-2 border-sd-line pb-0.5 pl-3.5 before:absolute before:top-[9px] before:-left-[5px] before:size-2 before:rounded-full before:border-2 before:border-sd-line-2 before:bg-sd-panel before:content-[''] data-[state=current]:border-l-sd-accent data-[state=current]:before:border-sd-accent data-[state=current]:before:bg-sd-accent data-[state=current]:before:shadow-[0_0_0_3px_var(--accent-soft)] data-[state=done]:border-l-[color-mix(in_srgb,var(--accent)_45%,transparent)] data-[state=done]:before:border-sd-accent data-[state=done]:before:bg-sd-accent"
        >
            <button
                type="button"
                data-testid="phase-head"
                :aria-expanded="phase.open"
                :aria-controls="`phase-items-${phase.index}`"
                class="flex w-full cursor-pointer items-baseline gap-2 py-[5px] text-left"
                @click="$emit('toggle-phase', phase.index)"
            >
                <span
                    data-testid="phase-number"
                    class="font-mono text-[10px] text-sd-ink-3 tabular-nums group-data-[state=current]:text-sd-accent group-data-[state=done]:text-sd-accent"
                >
                    {{ String(phase.number).padStart(2, '0') }}
                </span>
                <span
                    data-testid="phase-name"
                    class="text-[13px] font-semibold tracking-[-0.005em] text-sd-ink-2 group-data-[state=current]:text-sd-ink"
                >
                    {{ phase.name }}
                </span>
                <span
                    data-testid="phase-progress"
                    class="ml-auto font-mono text-[10px] text-sd-ink-3 tabular-nums"
                >
                    {{ phase.done }}/{{ phase.total }}
                </span>
                <span
                    data-testid="phase-minutes"
                    class="font-mono text-[10px] text-sd-ink-3"
                >
                    {{ phase.minutes }} min
                </span>
            </button>

            <!-- Os itens ficam montados mesmo colapsados: a fase que passou
                 continua marcável assim que o usuário a reabre. -->
            <div
                v-show="phase.open"
                :id="`phase-items-${phase.index}`"
                data-testid="phase-items"
                class="pt-0.5 pb-2.5"
            >
                <ChecklistItem
                    v-for="item in phase.items"
                    :key="item.id"
                    :item-id="item.id"
                    :content="item.content"
                    :checked="item.checked"
                    @toggle="$emit('toggle-item', item.id)"
                />
            </div>
        </div>
    </div>
</template>
