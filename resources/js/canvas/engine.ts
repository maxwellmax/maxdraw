import type {
    CatalogCategory,
    CatalogComponent,
    CatalogEntry,
    ComponentIndex,
} from './catalog';
import { colorOf, indexComponents, shortNameOf } from './catalog';
import {
    addEdge,
    addNode,
    moveNode,
    removeEdge,
    removeNode,
    renameNode,
    restore,
    snapshot,
} from './diagram';
import { bez, head, NODE_HEIGHT } from './geometry';
import { edgeById, nodeById, nodeHeight } from './nodes';
import type {
    ArrowHead,
    Edge,
    EdgeGeometry,
    MutationResult,
    Node,
    NodeHeights,
    Selection,
    SelectionKind,
    SequenceMode,
    SessionState,
    Size,
    View,
} from './types';
import { UndoStack } from './undo';
import {
    centerSpot,
    DEFAULT_VIEW,
    fitView,
    panBy,
    toWorld,
    wheelZoom,
    zoomAt,
} from './view';

type Drag = {
    id: string;
    offsetX: number;
    offsetY: number;
    moved: boolean;
};

/**
 * O motor do canvas. Concentra o diagrama, o enquadramento, a seleção e a pilha
 * de desfazer num objeto só, e nenhuma parte dele conhece Vue ou o DOM — a
 * camada de tela mede alturas e traduz eventos, o motor decide o resto.
 *
 * Só o que muda o diagrama empilha desfazer. Pan, zoom, seleção e modo de
 * numeração mudam o que se vê, não o que foi desenhado (US-3.5, US-4.3).
 */
export class CanvasEngine {
    state: SessionState;

    view: View = { ...DEFAULT_VIEW };

    selection: Selection | null = null;

    /** As alturas reais medidas na tela, por bloco. */
    heights: NodeHeights = {};

    size: Size = { width: 0, height: 0 };

    /** A largura que a legenda expandida ocupa; zero quando ela está recolhida. */
    legendWidth = 0;

    private history = new UndoStack();

    private index: ComponentIndex;

    private drag: Drag | null = null;

    constructor(
        state: SessionState,
        categories: readonly CatalogCategory[] = [],
    ) {
        this.state = state;
        this.index = indexComponents(categories);
    }

    get nodes(): Node[] {
        return this.state.nodes;
    }

    get edges(): Edge[] {
        return this.state.edges;
    }

    get seqMode(): SequenceMode {
        return this.state.seqMode;
    }

    get isEmpty(): boolean {
        return this.state.nodes.length === 0;
    }

    get canUndo(): boolean {
        return this.history.canUndo;
    }

    get canRedo(): boolean {
        return this.history.canRedo;
    }

    get undoDepth(): number {
        return this.history.size;
    }

    get redoDepth(): number {
        return this.history.redoSize;
    }

    get isDragging(): boolean {
        return this.drag !== null;
    }

    setCatalog(categories: readonly CatalogCategory[]): void {
        this.index = indexComponents(categories);
    }

    setSize(size: Size): void {
        this.size = size;
    }

    setLegendWidth(width: number): void {
        this.legendWidth = width;
    }

    measure(id: string, height: number): void {
        this.heights[id] = height;
    }

    node(id: string): Node | null {
        return nodeById(this.state.nodes, id);
    }

    edge(id: string): Edge | null {
        return edgeById(this.state.edges, id);
    }

    heightOf(node: Node): number {
        return nodeHeight(node, this.heights);
    }

    entry(slug: string): CatalogEntry | null {
        return this.index.get(slug) ?? null;
    }

    component(slug: string): CatalogComponent | null {
        return this.entry(slug)?.component ?? null;
    }

    color(slug: string): string {
        return colorOf(this.index, slug);
    }

    shortName(slug: string): string {
        return shortNameOf(this.index, slug);
    }

    geometry(edge: Edge): EdgeGeometry | null {
        return bez(edge, this.state.nodes, this.heights);
    }

    /** A ponta da seta encosta no destino, deitada sobre a tangente que chega lá. */
    arrowHead(geometry: EdgeGeometry): ArrowHead {
        return head(geometry.x2, geometry.y2, geometry.c2[0], geometry.c2[1]);
    }

    select(selection: Selection | null): void {
        this.selection = selection;
    }

    selectNode(id: string): void {
        this.selection = { kind: 'node', id };
    }

    isSelected(kind: SelectionKind, id: string): boolean {
        return this.selection?.kind === kind && this.selection.id === id;
    }

    /**
     * Coloca um bloco do tipo pedido no meio da área visível. O rótulo nasce
     * com o nome curto do componente e o bloco já entra selecionado, para a
     * tela abrir o campo de nome focado (US-3.1).
     */
    addNode(slug: string): MutationResult<Node> {
        const component = this.component(slug);

        if (!component) {
            return { ok: false, reason: 'unknownComponent' };
        }

        const previous = snapshot(this.state);
        const result = addNode(
            this.state,
            component,
            centerSpot(this.view, this.size, NODE_HEIGHT),
        );

        if (result.ok) {
            this.history.push(previous);
            this.selectNode(result.value.id);
        }

        return result;
    }

    addEdge(from: string, to: string): MutationResult<Edge> {
        const previous = snapshot(this.state);
        const before = this.state.edges.length;
        const result = addEdge(this.state, from, to);

        if (result.ok && this.state.edges.length > before) {
            this.history.push(previous);
            this.selection = { kind: 'edge', id: result.value.id };
        }

        return result;
    }

    /** Rótulo vazio volta ao nome curto do componente (US-3.2). */
    renameNode(id: string, label: string): boolean {
        const node = this.node(id);

        if (!node) {
            return false;
        }

        const previous = snapshot(this.state);
        const changed = renameNode(
            this.state,
            id,
            label,
            this.shortName(node.type),
        );

        if (changed) {
            this.history.push(previous);
        }

        return changed;
    }

    /** Apagar um bloco leva junto todas as arestas ligadas a ele (US-3.5). */
    deleteSelection(): boolean {
        const selection = this.selection;

        if (!selection) {
            return false;
        }

        const previous = snapshot(this.state);
        const removed =
            selection.kind === 'node'
                ? removeNode(this.state, selection.id)
                : removeEdge(this.state, selection.id);

        if (!removed) {
            return false;
        }

        this.history.push(previous);
        this.selection = null;

        return true;
    }

    beginDrag(id: string, worldX: number, worldY: number): void {
        const node = this.node(id);

        if (!node) {
            return;
        }

        this.drag = {
            id,
            offsetX: worldX - node.x,
            offsetY: worldY - node.y,
            moved: false,
        };
    }

    /**
     * Arrasta o bloco até o ponteiro, com snap de 4 px. O desfazer só entra na
     * primeira posição que realmente mudou: um clique que não moveu nada não
     * gasta um passo da pilha.
     */
    dragTo(worldX: number, worldY: number): boolean {
        const drag = this.drag;

        if (!drag) {
            return false;
        }

        const previous = snapshot(this.state);
        const moved = moveNode(
            this.state,
            drag.id,
            worldX - drag.offsetX,
            worldY - drag.offsetY,
        );

        if (moved && !drag.moved) {
            drag.moved = true;
            this.history.push(previous);
        }

        return moved;
    }

    endDrag(): boolean {
        const moved = this.drag?.moved ?? false;

        this.drag = null;

        return moved;
    }

    setSequenceMode(mode: SequenceMode): boolean {
        if (this.state.seqMode === mode) {
            return false;
        }

        this.state.seqMode = mode;

        return true;
    }

    panBy(dx: number, dy: number): void {
        this.view = panBy(this.view, dx, dy);
    }

    setView(view: View): void {
        this.view = view;
    }

    zoomAt(factor: number, anchorX: number, anchorY: number): void {
        this.view = zoomAt(this.view, factor, anchorX, anchorY);
    }

    /** Os botões de mais e menos ancoram no centro do palco. */
    zoomBy(factor: number): void {
        this.zoomAt(factor, this.size.width / 2, this.size.height / 2);
    }

    wheel(deltaY: number, anchorX: number, anchorY: number): void {
        this.view = wheelZoom(this.view, deltaY, anchorX, anchorY);
    }

    fit(): void {
        this.view = fitView(this.state.nodes, this.size, {
            heights: this.heights,
            legendWidth: this.legendWidth,
        });
    }

    toWorld(x: number, y: number): [number, number] {
        return toWorld(this.view, x, y);
    }

    undo(): boolean {
        const previous = this.history.undo(snapshot(this.state));

        if (!previous) {
            return false;
        }

        restore(this.state, previous);
        this.selection = null;

        return true;
    }

    redo(): boolean {
        const next = this.history.redo(snapshot(this.state));

        if (!next) {
            return false;
        }

        restore(this.state, next);
        this.selection = null;

        return true;
    }
}
