<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ComponentPublicInstance } from 'vue';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { CanvasEngine } from '@/canvas/engine';
import type { SequenceMode } from '@/canvas/types';
import { LEGEND_WIDTH } from '@/canvas/view';
import BoardTopBar from '@/components/prancheta/BoardTopBar.vue';
import CanvasNode from '@/components/prancheta/CanvasNode.vue';
import CanvasWire from '@/components/prancheta/CanvasWire.vue';
import ComponentPalette from '@/components/prancheta/ComponentPalette.vue';
import ComponentRail from '@/components/prancheta/ComponentRail.vue';
import DrillClock from '@/components/prancheta/DrillClock.vue';
import DrillPanel from '@/components/prancheta/DrillPanel.vue';
import ModalSheet from '@/components/prancheta/ModalSheet.vue';
import NarrowNotice from '@/components/prancheta/NarrowNotice.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import StageCanvas from '@/components/prancheta/StageCanvas.vue';
import ToastHost from '@/components/prancheta/ToastHost.vue';
import { createSessionStore, useAutosave } from '@/composables/useAutosave';
import { useStageInteraction } from '@/composables/useStageInteraction';
import { useTheme } from '@/composables/useTheme';
import { useToast } from '@/composables/useToast';
import type { SessionStore } from '@/prancheta/session';
import type { DrillTabId } from '@/prancheta/tabs';
import { DEFAULT_DRILL_TAB } from '@/prancheta/tabs';
import type { BoardCatalog, SessionPayload } from '@/types';

const props = defineProps<{
    session: SessionPayload;
    catalog: BoardCatalog;
}>();

const { toggle: toggleTheme } = useTheme();
const { warn } = useToast();

/**
 * O store é a fonte única do que é persistido, e o motor governa o diagrama
 * dentro dele: os dois trabalham sobre o mesmo objeto de estado, então
 * desenhar já suja a sessão. Nenhuma regra de canvas nem payload de autosave
 * mora nesta página.
 *
 * O estado entra copiado da resposta do servidor: desenhar não pode reescrever
 * aquilo que o Inertia guarda no histórico.
 */
const store = reactive(createSessionStore(props.session)) as SessionStore;

const engine = reactive(
    new CanvasEngine(store.state, props.catalog.component_categories),
) as CanvasEngine;

const { autosave, saveNow } = useAutosave(store);

const activeTab = ref<DrillTabId>(DEFAULT_DRILL_TAB);

const running = ref(false);

const openSheet = ref<'problem' | 'sessions' | null>(null);

const stage = ref<InstanceType<typeof StageCanvas> | null>(null);
const stageElement = computed(() => stage.value?.el ?? null);

const nodeComponents = new Map<string, { beginEdit: () => void }>();

useStageInteraction(engine, stageElement, { onEditRequest: editNode });

/** A legenda cobre o canto direito do palco, e enquadrar tudo desconta isso. */
watch(
    () => engine.isEmpty,
    (isEmpty) => engine.setLegendWidth(isEmpty ? 0 : LEGEND_WIDTH),
    { immediate: true },
);

watch(
    () => props.catalog.component_categories,
    (categories) => engine.setCatalog(categories),
);

const wires = computed(() =>
    engine.edges.flatMap((edge) => {
        const geometry = engine.geometry(edge);

        if (!geometry) {
            return [];
        }

        return [
            {
                id: edge.id,
                geometry,
                headPath: engine.arrowHead(geometry).d,
                color: engine.color(engine.node(edge.from)?.type ?? ''),
                selected: engine.isSelected('edge', edge.id),
            },
        ];
    }),
);

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
        ticker = setInterval(
            () => store.setElapsedSeconds(store.elapsedSeconds + 1),
            1000,
        );
    }
});

onBeforeUnmount(stopTicker);

function resetClock(): void {
    running.value = false;
    store.setElapsedSeconds(0);
}

function exportSvg(): void {
    if (engine.isEmpty) {
        warn('exportEmptyCanvas');
    }
}

function setNodeComponent(
    id: string,
    instance: Element | ComponentPublicInstance | null,
): void {
    if (instance) {
        nodeComponents.set(
            id,
            instance as unknown as { beginEdit: () => void },
        );

        return;
    }

    nodeComponents.delete(id);
}

function editNode(id: string): void {
    nextTick(() => nodeComponents.get(id)?.beginEdit());
}

/**
 * Um clique na paleta coloca o bloco e abre o campo de nome já focado. No
 * limite de 200 o bloco não entra e o usuário fica sabendo pelo toast (US-3.1).
 */
function placeNode(slug: string): void {
    const result = engine.addNode(slug);

    if (!result.ok) {
        if (result.reason === 'nodeLimitReached') {
            warn(result.reason);
        }

        return;
    }

    editNode(result.value.id);
}

const SEQUENCE_CYCLE: SequenceMode[] = ['out', 'flow', 'off'];

function cycleSequenceMode(): void {
    const next =
        SEQUENCE_CYCLE[
            (SEQUENCE_CYCLE.indexOf(engine.seqMode) + 1) % SEQUENCE_CYCLE.length
        ];

    engine.setSequenceMode(next);
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
                :save-state="autosave.chip"
                :save-label="autosave.label ?? undefined"
                @pick-problem="openSheet = 'problem'"
                @open-sessions="openSheet = 'sessions'"
                @toggle-theme="toggleTheme"
                @export-svg="exportSvg"
                @save="saveNow"
            />

            <ComponentRail>
                <ComponentPalette
                    :categories="catalog.component_categories"
                    @pick="placeNode"
                />
            </ComponentRail>

            <StageCanvas
                ref="stage"
                :empty="engine.isEmpty"
                :scale="engine.view.k"
                :offset-x="engine.view.x"
                :offset-y="engine.view.y"
                :legend-visible="!engine.isEmpty"
                @zoom-in="engine.zoomBy(1.2)"
                @zoom-out="engine.zoomBy(1 / 1.2)"
                @fit="engine.fit()"
                @cycle-sequence="cycleSequenceMode"
            >
                <template #wires>
                    <CanvasWire
                        v-for="wire in wires"
                        :key="wire.id"
                        :edge-id="wire.id"
                        :geometry="wire.geometry"
                        :head-path="wire.headPath"
                        :color="wire.color"
                        :selected="wire.selected"
                    />
                </template>

                <template #nodes>
                    <CanvasNode
                        v-for="node in engine.nodes"
                        :key="node.id"
                        :ref="(instance) => setNodeComponent(node.id, instance)"
                        :node="node"
                        :color="engine.color(node.type)"
                        :icon-key="engine.component(node.type)?.icon_key ?? ''"
                        :type-name="engine.shortName(node.type)"
                        :selected="engine.isSelected('node', node.id)"
                        @rename="engine.renameNode(node.id, $event)"
                        @measure="engine.measure(node.id, $event)"
                    />
                </template>
            </StageCanvas>

            <DrillPanel v-model="activeTab">
                <template #clock>
                    <DrillClock
                        :elapsed-seconds="store.elapsedSeconds"
                        :duration-minutes="store.durationMinutes"
                        :running="running"
                        @toggle="running = !running"
                        @reset="resetClock"
                        @select-duration="store.setDurationMinutes($event)"
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
