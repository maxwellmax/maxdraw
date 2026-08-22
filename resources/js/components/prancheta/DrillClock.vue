<script setup lang="ts">
import { computed } from 'vue';

export type PhaseSegment = {
    weight: number;
    state: 'done' | 'current' | 'todo';
    progress?: number;
};

const props = withDefaults(
    defineProps<{
        elapsedSeconds?: number;
        durationMinutes?: number;
        running?: boolean;
        durations?: readonly number[];
        phaseNumber?: number | null;
        phaseName?: string | null;
        segments?: readonly PhaseSegment[];
    }>(),
    {
        elapsedSeconds: 0,
        durationMinutes: 45,
        running: false,
        durations: () => [30, 45, 60],
        phaseNumber: null,
        phaseName: null,
        segments: () => [],
    },
);

defineEmits<{
    toggle: [];
    reset: [];
    'select-duration': [minutes: number];
}>();

function formatClock(totalSeconds: number): string {
    const safe = Math.max(0, Math.floor(totalSeconds));
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

const elapsed = computed(() => formatClock(props.elapsedSeconds));
const total = computed(() => formatClock(props.durationMinutes * 60));
</script>

<template>
    <div
        data-testid="clock"
        class="border-b border-sd-line bg-sd-panel px-4 pt-3.5 pb-3"
    >
        <div class="flex items-baseline gap-2.5">
            <div
                data-testid="clock-elapsed"
                class="font-mono text-[34px] leading-none font-medium tracking-[-0.02em] text-sd-ink tabular-nums"
            >
                {{ elapsed }}
            </div>
            <div
                data-testid="clock-total"
                class="font-mono text-[11px] text-sd-ink-3"
            >
                de {{ total }}
            </div>
            <div class="ml-auto flex gap-1">
                <button
                    type="button"
                    data-testid="clock-toggle"
                    :title="running ? 'Pausar' : 'Iniciar'"
                    :aria-label="
                        running ? 'Pausar o cronômetro' : 'Iniciar o cronômetro'
                    "
                    class="grid size-[30px] cursor-pointer place-items-center rounded-md border border-sd-accent bg-sd-accent text-sd-accent-ink hover:border-sd-accent-2 hover:bg-sd-accent-2 [&_svg]:size-3.5"
                    @click="$emit('toggle')"
                >
                    <svg
                        v-if="running"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M8 5.5h3v13H8zM13 5.5h3v13h-3z" />
                    </svg>
                    <svg
                        v-else
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M8 5.5v13l11-6.5z" />
                    </svg>
                </button>
                <button
                    type="button"
                    data-testid="clock-reset"
                    title="Zerar o cronômetro"
                    aria-label="Zerar o cronômetro"
                    class="grid size-[30px] cursor-pointer place-items-center rounded-md border border-sd-line-2 text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink [&_svg]:size-3.5"
                    @click="$emit('reset')"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M4.5 9.5A8 8 0 1 1 4 13" />
                        <path d="M4 5v4.5h4.5" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            v-if="phaseName"
            data-testid="clock-phase"
            class="mt-[9px] flex items-center gap-[7px] text-xs text-sd-ink-2"
        >
            <span class="font-mono text-[10px] font-semibold text-sd-accent">
                {{ String(phaseNumber ?? 0).padStart(2, '0') }}
            </span>
            <span>{{ phaseName }}</span>
        </div>

        <div
            data-testid="clock-bar"
            class="mt-[9px] flex h-[5px] gap-[1.5px] overflow-hidden rounded-sm bg-sd-panel-3"
        >
            <span
                v-for="(segment, index) in segments"
                :key="index"
                class="relative block h-full overflow-hidden rounded-sm bg-sd-line-2"
                :class="{ 'bg-sd-accent opacity-45': segment.state === 'done' }"
                :style="{ flex: segment.weight }"
            >
                <i
                    v-if="segment.state === 'current'"
                    class="absolute inset-y-0 left-0 block bg-sd-accent"
                    :style="{ width: `${(segment.progress ?? 0) * 100}%` }"
                />
            </span>
        </div>

        <div data-testid="duration-picker" class="mt-2.5 flex gap-1">
            <button
                v-for="minutes in durations"
                :key="minutes"
                type="button"
                :data-testid="`duration-${minutes}`"
                :aria-pressed="minutes === durationMinutes"
                class="h-6 flex-1 cursor-pointer rounded-[5px] border border-sd-line font-mono text-[11px] text-sd-ink-3 hover:border-sd-line-2 hover:text-sd-ink-2 aria-pressed:border-sd-accent aria-pressed:bg-sd-accent-soft aria-pressed:font-semibold aria-pressed:text-sd-accent"
                @click="$emit('select-duration', minutes)"
            >
                {{ minutes }} min
            </button>
        </div>
    </div>
</template>
