<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ComponentPublicInstance } from 'vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { CanvasEngine } from '@/canvas/engine';
import { ptAt } from '@/canvas/geometry';
import type { EdgeFlag, RefusalReason } from '@/canvas/types';
import { LEGEND_WIDTH } from '@/canvas/view';
import BoardTopBar from '@/components/prancheta/BoardTopBar.vue';
import CanvasNode from '@/components/prancheta/CanvasNode.vue';
import CanvasWire from '@/components/prancheta/CanvasWire.vue';
import ComponentPalette from '@/components/prancheta/ComponentPalette.vue';
import ComponentRail from '@/components/prancheta/ComponentRail.vue';
import DrillClock from '@/components/prancheta/DrillClock.vue';
import DrillPanel from '@/components/prancheta/DrillPanel.vue';
import EdgeChip from '@/components/prancheta/EdgeChip.vue';
import EdgeFloatBar from '@/components/prancheta/EdgeFloatBar.vue';
import LegendContent from '@/components/prancheta/LegendContent.vue';
import ModalSheet from '@/components/prancheta/ModalSheet.vue';
import NarrowNotice from '@/components/prancheta/NarrowNotice.vue';
import NotesPad from '@/components/prancheta/NotesPad.vue';
import PhaseAccordion from '@/components/prancheta/PhaseAccordion.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import ProblemBrief from '@/components/prancheta/ProblemBrief.vue';
import ProblemPicker from '@/components/prancheta/ProblemPicker.vue';
import StageCanvas from '@/components/prancheta/StageCanvas.vue';
import ToastHost from '@/components/prancheta/ToastHost.vue';
import { createSessionStore, useAutosave } from '@/composables/useAutosave';
import { useClock } from '@/composables/useClock';
import { useLegend } from '@/composables/useLegend';
import { useStageInteraction } from '@/composables/useStageInteraction';
import { useTheme } from '@/composables/useTheme';
import { useToast } from '@/composables/useToast';
import {
    bounds,
    curPhase,
    durationsFrom,
    phaseSegments,
} from '@/prancheta/clock';
import {
    opensPickerOnLoad,
    PICKER_DELAY_MS,
    problemOf,
} from '@/prancheta/problems';
import type { PhaseChoice } from '@/prancheta/roteiro';
import { FOLLOW_CURRENT, phaseRows, toggleChoice } from '@/prancheta/roteiro';
import type { SessionStore } from '@/prancheta/session';
import type { DrillTabId } from '@/prancheta/tabs';
import { DEFAULT_DRILL_TAB } from '@/prancheta/tabs';
import type { BoardCatalog, SessionPayload } from '@/types';

const props = defineProps<{
    session: SessionPayload;
    catalog: BoardCatalog;
}>();

const { toggle: toggleTheme } = useTheme();
const { open: legendOpen } = useLegend();
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
    new CanvasEngine(
        store.state,
        props.catalog.component_categories,
        props.catalog.link_types,
        props.catalog.sequence_modes,
    ),
) as CanvasEngine;

const { autosave, saveNow } = useAutosave(store);

const { clock } = useClock(store, autosave);

const activeTab = ref<DrillTabId>(DEFAULT_DRILL_TAB);

/**
 * Qual fase o usuário abriu à mão. `FOLLOW_CURRENT` devolve o acordeão ao
 * cronômetro, e é para lá que ele volta a cada virada de fase (US-6.2).
 */
const phaseChoice = ref<PhaseChoice>(FOLLOW_CURRENT);

const openSheet = ref<'problem' | 'sessions' | null>(null);

/**
 * O enunciado da sessão. Nenhum texto de problema mora na página: o que a
 * sessão guarda é o id, e o catálogo entrega o resto (US-2.1).
 */
const problem = computed(() =>
    problemOf(props.catalog.problems, store.problemId),
);

/**
 * Sessão que ainda não começou — sem problema e sem blocos — abre o seletor
 * sozinha, com o atraso do protótipo. Quem fecha sem escolher continua na
 * prancheta livre.
 */
let pickerTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    if (!opensPickerOnLoad(store.problemId, store.nodes.length)) {
        return;
    }

    pickerTimer = setTimeout(
        () => (openSheet.value = 'problem'),
        PICKER_DELAY_MS,
    );
});

onBeforeUnmount(() => {
    if (pickerTimer) {
        clearTimeout(pickerTimer);
    }
});

const stage = ref<InstanceType<typeof StageCanvas> | null>(null);
const stageElement = computed(() => stage.value?.el ?? null);

const nodeComponents = new Map<string, { beginEdit: () => void }>();

const chipComponents = new Map<string, { beginEdit: () => void }>();

useStageInteraction(engine, stageElement, {
    onEditRequest: editNode,
    onRefused: warnAbout,
});

/**
 * A legenda inteira sai do desenho: nada aqui a configura, e ela some por
 * completo quando não há o que explicar (US-5.1).
 */
const legend = computed(() => engine.legendData());

/**
 * A legenda cobre o canto direito do palco, e enquadrar tudo desconta isso —
 * mas só enquanto ela estiver aberta e com conteúdo (US-5.2).
 */
watch(
    [() => legend.value.empty, legendOpen],
    ([empty, open]) => engine.setLegendWidth(empty || !open ? 0 : LEGEND_WIDTH),
    { immediate: true },
);

watch(
    () => props.catalog.component_categories,
    (categories) => engine.setCatalog(categories),
);

watch(
    () => props.catalog.link_types,
    (linkTypes) => engine.setLinkTypes(linkTypes),
);

watch(
    () => props.catalog.sequence_modes,
    (modes) => engine.setSequenceModes(modes),
);

/**
 * As setas desenhadas. A aresta órfã que sobrou de um desfazer parcial fica de
 * fora do desenho sem sair do estado, e a cor vem sempre da categoria do bloco
 * de origem — nunca do tipo da ligação (US-4.1).
 */
const wires = computed(() => {
    const numbers = engine.seqMap();

    return engine.liveEdges().flatMap((edge) => {
        const geometry = engine.geometry(edge);

        if (!geometry) {
            return [];
        }

        const [midX, midY] = ptAt(geometry, 0.5);
        const chip = engine.edgeChip(edge);

        return [
            {
                id: edge.id,
                geometry,
                headPath: engine.arrowHead(geometry).d,
                tailPath: edge.bidir ? engine.arrowTail(geometry).d : null,
                dash: engine.dashOf(edge),
                color: engine.edgeColor(edge),
                selected: engine.isSelected('edge', edge.id),
                chip,
                seq: numbers[edge.id] ?? null,
                midX,
                midY,
            },
        ];
    });
});

const selectedEdge = computed(() =>
    engine.selection?.kind === 'edge' ? engine.edge(engine.selection.id) : null,
);

/**
 * A barra flutuante da seta selecionada. O apoio dela sai do enquadramento
 * corrente, então ela acompanha pan e zoom sem guardar posição própria.
 */
const edgeBar = computed(() => {
    const edge = selectedEdge.value;
    const anchor = edge ? engine.edgeAnchor(edge) : null;

    if (!edge || !anchor) {
        return null;
    }

    return {
        edge,
        anchor,
        badge: engine.edgeChip(edge).badge,
        seq: engine.outSeq()[edge.id] ?? null,
    };
});

/**
 * O roteiro fatiado: os pesos das cinco fases do catálogo aplicados sobre a
 * duração escolhida. Trocar a duração recalcula as fatias sem tocar no tempo
 * já decorrido (US-6.1, US-2.3).
 */
const phaseIndex = computed(() =>
    curPhase(
        store.elapsedSeconds,
        bounds(store.durationMinutes, props.catalog.phases),
    ),
);

const currentPhase = computed(
    () => props.catalog.phases[phaseIndex.value] ?? null,
);

const segments = computed(() =>
    phaseSegments(
        store.elapsedSeconds,
        store.durationMinutes,
        props.catalog.phases,
    ),
);

const durations = computed(() =>
    durationsFrom(props.catalog.session_durations),
);

/**
 * O acordeão do roteiro: as cinco fases com os itens do catálogo, o progresso
 * de cada uma e os minutos que a duração escolhida reserva para ela (US-6.2).
 */
const roteiro = computed(() =>
    phaseRows(
        props.catalog.phases,
        store.checks,
        store.elapsedSeconds,
        store.durationMinutes,
        phaseChoice.value,
    ),
);

/** A virada de fase desfaz a escolha manual e abre a fase que começou agora. */
watch(phaseIndex, () => (phaseChoice.value = FOLLOW_CURRENT));

const sheetTitle = computed(() =>
    openSheet.value === 'sessions' ? 'Sessões' : 'Escolher um problema',
);

const sheetDescription = computed(() =>
    openSheet.value === 'sessions'
        ? 'Abra uma sessão salva para restaurar o treino de onde parou.'
        : 'Cada um vem com enunciado, requisitos e a escala alvo. O treino corrente continua de onde está.',
);

/** Escolher o problema grava-o na sessão corrente e fecha a folha (US-2.1). */
function pickProblem(problemId: number | null): void {
    store.setProblemId(problemId);
    openSheet.value = null;
}

function pickPhase(index: number): void {
    phaseChoice.value = toggleChoice(
        phaseChoice.value,
        phaseIndex.value,
        index,
    );
}

/** Ausência é desmarcado, então alternar é sempre contra o que está gravado. */
function toggleCheck(itemId: number): void {
    store.setCheck(itemId, !store.checks[String(itemId)]);
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

function setChipComponent(
    id: string,
    instance: Element | ComponentPublicInstance | null,
): void {
    if (instance) {
        chipComponents.set(
            id,
            instance as unknown as { beginEdit: () => void },
        );

        return;
    }

    chipComponents.delete(id);
}

/** Recusa silenciosa não vira aviso; só o limite de 400 tem o que dizer. */
function warnAbout(reason: RefusalReason): void {
    if (reason === 'edgeLimitReached' || reason === 'nodeLimitReached') {
        warn(reason);
    }
}

function editEdgeLabel(id: string): void {
    nextTick(() => chipComponents.get(id)?.beginEdit());
}

function pickEdgeKind(id: string, kind: string | null): void {
    engine.setEdgeKind(id, kind);
}

/**
 * Um clique na paleta coloca o bloco e abre o campo de nome já focado. No
 * limite de 200 o bloco não entra e o usuário fica sabendo pelo toast (US-3.1).
 */
function placeNode(slug: string): void {
    const result = engine.addNode(slug);

    if (!result.ok) {
        warnAbout(result.reason);

        return;
    }

    editNode(result.value.id);
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
                :problem-name="problem?.name ?? null"
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
                :legend-visible="!legend.empty"
                :sequence-mode="engine.seqMode"
                :sequence-modes="engine.sequenceModes"
                @zoom-in="engine.zoomBy(1.2)"
                @zoom-out="engine.zoomBy(1 / 1.2)"
                @fit="engine.fit()"
                @pick-sequence="engine.setSequenceMode($event)"
            >
                <template #wires>
                    <CanvasWire
                        v-for="wire in wires"
                        :key="wire.id"
                        :edge-id="wire.id"
                        :geometry="wire.geometry"
                        :head-path="wire.headPath"
                        :tail-path="wire.tailPath"
                        :dash="wire.dash"
                        :color="wire.color"
                        :selected="wire.selected"
                    />

                    <path
                        v-if="engine.ghost"
                        data-testid="link-ghost"
                        :d="engine.ghost.d"
                        fill="none"
                        stroke="var(--accent)"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-dasharray="4 4"
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
                        :link-target="engine.isLinkTarget(node.id)"
                        @rename="engine.renameNode(node.id, $event)"
                        @measure="engine.measure(node.id, $event)"
                    />
                </template>

                <template #labels>
                    <EdgeChip
                        v-for="wire in wires"
                        :key="wire.id"
                        :ref="(instance) => setChipComponent(wire.id, instance)"
                        :edge-id="wire.id"
                        :badge="wire.chip.badge"
                        :label="wire.chip.label"
                        :bare="wire.chip.bare"
                        :seq="wire.seq"
                        :color="wire.color"
                        :x="wire.midX"
                        :y="wire.midY"
                        @rename="engine.setEdgeLabel(wire.id, $event)"
                    />
                </template>

                <template #legend>
                    <LegendContent :data="legend" />
                </template>

                <template #overlay>
                    <EdgeFloatBar
                        v-if="edgeBar"
                        :edge-id="edgeBar.edge.id"
                        :anchor="edgeBar.anchor"
                        :stage="engine.size"
                        :types="engine.linkTypes"
                        :kind="edgeBar.edge.kind"
                        :badge="edgeBar.badge"
                        :dashed="edgeBar.edge.dashed"
                        :bidir="edgeBar.edge.bidir"
                        :seq="edgeBar.seq"
                        @pick-kind="pickEdgeKind(edgeBar.edge.id, $event)"
                        @edit-label="editEdgeLabel(edgeBar.edge.id)"
                        @toggle="
                            engine.toggleEdgeFlag(
                                edgeBar.edge.id,
                                $event as EdgeFlag,
                            )
                        "
                        @reverse="engine.reverseEdge(edgeBar.edge.id)"
                        @move-seq="engine.moveSeq(edgeBar.edge.id, $event)"
                        @remove="engine.deleteSelection()"
                    />
                </template>
            </StageCanvas>

            <DrillPanel v-model="activeTab">
                <template #roteiro>
                    <PhaseAccordion
                        :phases="roteiro"
                        @toggle-phase="pickPhase"
                        @toggle-item="toggleCheck"
                    />
                </template>

                <template #notas>
                    <NotesPad
                        :model-value="store.notes"
                        @update:model-value="store.setNotes($event)"
                        @blocked="warn('notesLimitReached')"
                    />
                </template>

                <template #enunciado>
                    <ProblemBrief :problem="problem" />
                </template>

                <template #clock>
                    <DrillClock
                        :elapsed-seconds="store.elapsedSeconds"
                        :duration-minutes="store.durationMinutes"
                        :durations="durations"
                        :running="clock.running"
                        :finished="clock.finished"
                        :tone="clock.tone"
                        :phase-number="currentPhase ? phaseIndex + 1 : null"
                        :phase-name="currentPhase?.name ?? null"
                        :segments="segments"
                        @toggle="clock.toggle()"
                        @reset="clock.reset()"
                        @select-duration="clock.setDurationMinutes($event)"
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

            <ProblemPicker
                v-if="openSheet === 'problem'"
                :problems="catalog.problems"
                :current="store.problemId"
                @pick="pickProblem"
            />

            <p v-else class="m-0 p-2 text-[12.5px] text-sd-ink-3">
                Nenhuma sessão carregada nesta prancheta.
            </p>

            <template v-if="openSheet === 'problem'" #footer>
                <span class="flex-1 text-[11.5px] text-sd-ink-3">
                    Fechar sem escolher mantém a prancheta livre — desenhar não
                    depende de um enunciado.
                </span>

                <PranchetaButton
                    data-testid="free-board"
                    @click="pickProblem(null)"
                >
                    Prancheta livre
                </PranchetaButton>
            </template>
        </ModalSheet>

        <ToastHost />
    </div>
</template>
