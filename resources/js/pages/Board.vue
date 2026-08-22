<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BoardTopBar from '@/components/prancheta/BoardTopBar.vue';
import ComponentRail from '@/components/prancheta/ComponentRail.vue';
import DrillClock from '@/components/prancheta/DrillClock.vue';
import DrillPanel from '@/components/prancheta/DrillPanel.vue';
import ModalSheet from '@/components/prancheta/ModalSheet.vue';
import NarrowNotice from '@/components/prancheta/NarrowNotice.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import StageCanvas from '@/components/prancheta/StageCanvas.vue';
import type { ChipState } from '@/components/prancheta/StateChip.vue';
import ToastHost from '@/components/prancheta/ToastHost.vue';
import { useTheme } from '@/composables/useTheme';
import { useToast } from '@/composables/useToast';
import type { DrillTabId } from '@/prancheta/tabs';
import { DEFAULT_DRILL_TAB } from '@/prancheta/tabs';

const { toggle: toggleTheme } = useTheme();
const { warn } = useToast();

const activeTab = ref<DrillTabId>(DEFAULT_DRILL_TAB);
const saveState = ref<ChipState>('saved');

const nodeCount = ref(0);
const scale = ref(1);

const elapsedSeconds = ref(0);
const durationMinutes = ref(45);
const running = ref(false);

const openSheet = ref<'problem' | 'sessions' | null>(null);

const sheetTitle = computed(() =>
    openSheet.value === 'sessions' ? 'Sessões' : 'Escolher um problema',
);

const sheetDescription = computed(() =>
    openSheet.value === 'sessions'
        ? 'Abra uma sessão salva para restaurar o treino de onde parou.'
        : 'Cada um vem com enunciado, requisitos e a escala alvo. O cronômetro zera.',
);

let ticker: ReturnType<typeof setInterval> | null = null;

function stopTicker(): void {
    if (ticker) {
        clearInterval(ticker);
        ticker = null;
    }
}

watch(running, (isRunning) => {
    stopTicker();

    if (isRunning) {
        ticker = setInterval(() => elapsedSeconds.value++, 1000);
    }
});

onBeforeUnmount(stopTicker);

function resetClock(): void {
    running.value = false;
    elapsedSeconds.value = 0;
}

function exportSvg(): void {
    if (nodeCount.value === 0) {
        warn('exportEmptyCanvas');
    }
}

function zoomBy(factor: number): void {
    scale.value = Math.min(2.4, Math.max(0.35, scale.value * factor));
}
</script>

<template>
    <div>
        <Head title="Prancheta" />

        <NarrowNotice />

        <div
            data-testid="board-shell"
            class="grid h-dvh grid-cols-[216px_1fr_348px] grid-rows-[48px_1fr] overflow-hidden max-[1080px]:grid-cols-[186px_1fr_300px] max-[860px]:hidden"
        >
            <BoardTopBar
                :save-state="saveState"
                @pick-problem="openSheet = 'problem'"
                @open-sessions="openSheet = 'sessions'"
                @toggle-theme="toggleTheme"
                @export-svg="exportSvg"
                @save="saveState = 'saved'"
            />

            <ComponentRail />

            <StageCanvas
                :empty="nodeCount === 0"
                :scale="scale"
                :legend-visible="nodeCount > 0"
                @zoom-in="zoomBy(1.1)"
                @zoom-out="zoomBy(1 / 1.1)"
                @fit="scale = 1"
            />

            <DrillPanel v-model="activeTab">
                <template #clock>
                    <DrillClock
                        :elapsed-seconds="elapsedSeconds"
                        :duration-minutes="durationMinutes"
                        :running="running"
                        @toggle="running = !running"
                        @reset="resetClock"
                        @select-duration="durationMinutes = $event"
                    />
                </template>
            </DrillPanel>
        </div>

        <ModalSheet
            :open="openSheet !== null"
            :title="sheetTitle"
            :description="sheetDescription"
            @update:open="openSheet = null"
        >
            <template #actions>
                <PranchetaButton
                    data-testid="sheet-close"
                    @click="openSheet = null"
                >
                    Fechar
                </PranchetaButton>
            </template>

            <p class="m-0 p-2 text-[12.5px] text-sd-ink-3">
                {{
                    openSheet === 'sessions'
                        ? 'Nenhuma sessão carregada nesta prancheta.'
                        : 'Nenhum problema carregado nesta prancheta.'
                }}
            </p>
        </ModalSheet>

        <ToastHost />
    </div>
</template>
