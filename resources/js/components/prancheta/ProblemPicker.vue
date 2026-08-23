<script setup lang="ts">
import { computed } from 'vue';
import type { LevelTone, ProblemOption } from '@/prancheta/problems';
import { problemCards } from '@/prancheta/problems';

const props = defineProps<{
    problems: readonly ProblemOption[];
    current: number | null;
}>();

defineEmits<{ pick: [problemId: number] }>();

/** O degrau de dificuldade é o único lugar do seletor com cor própria. */
const TONE_CLASSES: Record<LevelTone, string> = {
    ok: 'text-sd-ok',
    warn: 'text-sd-warn',
    crit: 'text-sd-crit',
};

const cards = computed(() => problemCards(props.problems));
</script>

<template>
    <div data-testid="problem-list" class="grid grid-cols-2 gap-2">
        <button
            v-for="card in cards"
            :key="card.id"
            type="button"
            data-testid="problem-card"
            :data-problem-id="card.id"
            :aria-current="card.id === current"
            class="cursor-pointer rounded-[9px] border border-sd-line bg-sd-panel-2 px-[13px] py-[11px] text-left transition-colors hover:border-sd-accent hover:bg-sd-panel aria-[current=true]:border-sd-accent"
            @click="$emit('pick', card.id)"
        >
            <b
                data-testid="problem-card-name"
                class="mb-0.5 block text-[13.5px] font-semibold tracking-[-0.01em] text-sd-ink"
            >
                {{ card.name }}
            </b>
            <span
                data-testid="problem-card-summary"
                class="block text-[11.5px] leading-[1.45] text-sd-ink-3"
            >
                {{ card.summary }}
            </span>
            <span
                data-testid="problem-card-level"
                class="mt-1.5 block font-sd-mono text-[9.5px] tracking-[0.08em] uppercase"
                :class="TONE_CLASSES[card.tone]"
            >
                {{ card.tag }} · {{ card.levelName }}
            </span>
        </button>
    </div>
</template>
