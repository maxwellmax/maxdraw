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
    moveSeq,
    removeEdge,
    removeNode,
    renameNode,
    restore,
    reverseEdge,
    setEdgeKind,
    setEdgeLabel,
    snapshot,
    toggleEdgeFlag,
} from './diagram';
import type { EdgeChip } from './edges';
import { dashOf, edgeChip, edgeColor, edgeOk, liveEdges } from './edges';
import { bez, ghostCurve, head, NODE_HEIGHT, ptAt } from './geometry';
import type { LegendData } from './legend';
import { legendData } from './legend';
import type { LinkType, LinkTypeIndex } from './links';
import { indexLinkTypes, linkTypeOf } from './links';
import { edgeById, nodeById, nodeHeight } from './nodes';
import type {
    SequenceMap,
    SequenceMenuItem,
    SequenceModeOption,
} from './sequence';
import { outSeq, seqMap, sequenceMenu, sequenceModeOf } from './sequence';
import type { SvgPalette } from './svg';
import { buildSVG } from './svg';
import type {
    ArrowHead,
    Edge,
    EdgeFlag,
    EdgeGeometry,
    LinkDrag,
    MutationResult,
    Node,
    NodeHeights,
    Point,
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
    FLOATBAR_LIFT,
    panBy,
    toScreen,
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

    private linkIndex: LinkTypeIndex;

    private linkDrag: LinkDrag | null = null;

    private sequenceModeOptions: readonly SequenceModeOption[] = [];

    constructor(
        state: SessionState,
        categories: readonly CatalogCategory[] = [],
        linkTypes: readonly LinkType[] = [],
        sequenceModes: readonly SequenceModeOption[] = [],
    ) {
        this.state = state;
        this.index = indexComponents(categories);
        this.linkIndex = indexLinkTypes(linkTypes);
        this.sequenceModeOptions = sequenceModes;
    }

    get nodes(): Node[] {
        return this.state.nodes;
    }

    get edges(): Edge[] {
        return this.state.edges;
    }

    get seqMode(): SequenceMode {
        return sequenceModeOf(this.state.seqMode);
    }

    /** As três opções do menu de numeração, na ordem em que a lista as mostra. */
    get sequenceModes(): SequenceMenuItem[] {
        return sequenceMenu(this.sequenceModeOptions);
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

    /** Os nove tipos na ordem do catálogo, que é a ordem do menu (US-4.1). */
    get linkTypes(): LinkType[] {
        return [...this.linkIndex.values()];
    }

    get isLinking(): boolean {
        return this.linkDrag !== null;
    }

    setCatalog(categories: readonly CatalogCategory[]): void {
        this.index = indexComponents(categories);
    }

    setLinkTypes(linkTypes: readonly LinkType[]): void {
        this.linkIndex = indexLinkTypes(linkTypes);
    }

    setSequenceModes(modes: readonly SequenceModeOption[]): void {
        this.sequenceModeOptions = modes;
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

    edgeOk(edge: Edge): boolean {
        return edgeOk(edge, this.state.nodes);
    }

    liveEdges(): Edge[] {
        return liveEdges(this.state.edges, this.state.nodes);
    }

    linkType(edge: Edge): LinkType | null {
        return linkTypeOf(this.linkIndex, edge.kind);
    }

    dashOf(edge: Edge): string | null {
        return dashOf(this.linkIndex, edge);
    }

    edgeColor(edge: Edge): string {
        return edgeColor(this.index, edge, this.state.nodes);
    }

    edgeChip(edge: Edge): EdgeChip {
        return edgeChip(this.linkIndex, edge);
    }

    /**
     * O ponto de apoio da barra flutuante, já em coordenadas de tela: o meio da
     * curva, um pouco acima dela. Como sai do enquadramento corrente, a barra
     * acompanha pan e zoom sem guardar posição própria (US-4.1).
     */
    edgeAnchor(edge: Edge): Point | null {
        const geometry = this.geometry(edge);

        if (!geometry) {
            return null;
        }

        const [x, y] = ptAt(geometry, 0.5);

        return toScreen(this.view, x, y - FLOATBAR_LIFT);
    }

    /** A ponta da seta encosta no destino, deitada sobre a tangente que chega lá. */
    arrowHead(geometry: EdgeGeometry): ArrowHead {
        return head(geometry.x2, geometry.y2, geometry.c2[0], geometry.c2[1]);
    }

    /** A segunda ponta, na origem, é o que desenha a mão dupla. */
    arrowTail(geometry: EdgeGeometry): ArrowHead {
        return head(geometry.x1, geometry.y1, geometry.c1[0], geometry.c1[1]);
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

    /**
     * Começa a ligação a partir de uma das bolinhas do bloco. Enquanto o arrasto
     * dura, `ghost` desenha a curva que segue o cursor e `linkTarget` diz qual
     * bloco está destacado como alvo.
     */
    beginLink(from: string, worldX: number, worldY: number): void {
        if (!this.node(from)) {
            return;
        }

        this.linkDrag = { from, x: worldX, y: worldY, target: null };
    }

    /** O bloco sob o cursor vira alvo — menos o próprio bloco de origem. */
    linkTo(worldX: number, worldY: number, over: string | null = null): void {
        const link = this.linkDrag;

        if (!link) {
            return;
        }

        link.x = worldX;
        link.y = worldY;
        link.target =
            over && over !== link.from && this.node(over) ? over : null;
    }

    get ghost(): EdgeGeometry | null {
        const link = this.linkDrag;
        const from = link ? this.node(link.from) : null;

        if (!link || !from) {
            return null;
        }

        return ghostCurve(from, this.heightOf(from), link.x, link.y);
    }

    get linkSource(): string | null {
        return this.linkDrag?.from ?? null;
    }

    get linkTarget(): string | null {
        return this.linkDrag?.target ?? null;
    }

    isLinkTarget(id: string): boolean {
        return this.linkDrag?.target === id;
    }

    /**
     * Solta a ligação. Fora de qualquer bloco — ou em cima do próprio bloco de
     * origem — não cria nada, e o `null` é justamente esse silêncio: não houve
     * recusa a avisar, houve desistência (US-3.3).
     */
    endLink(): MutationResult<Edge> | null {
        const link = this.linkDrag;

        this.linkDrag = null;

        if (!link || !link.target) {
            return null;
        }

        return this.addEdge(link.from, link.target);
    }

    cancelLink(): void {
        this.linkDrag = null;
    }

    setEdgeKind(id: string, kind: string | null): boolean {
        return this.mutateEdge(() =>
            setEdgeKind(this.state, id, kind, this.linkIndex),
        );
    }

    setEdgeLabel(id: string, label: string): boolean {
        return this.mutateEdge(() => setEdgeLabel(this.state, id, label));
    }

    toggleEdgeFlag(id: string, flag: EdgeFlag): boolean {
        return this.mutateEdge(() => toggleEdgeFlag(this.state, id, flag));
    }

    reverseEdge(id: string): boolean {
        return this.mutateEdge(() => reverseEdge(this.state, id));
    }

    /** Tipo, rótulo e bandeiras mudam o desenho, então todas empilham desfazer. */
    private mutateEdge(change: () => boolean): boolean {
        const previous = snapshot(this.state);
        const changed = change();

        if (changed) {
            this.history.push(previous);
        }

        return changed;
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

    /**
     * O número de cada seta no modo corrente. Mudar de modo não desenha nada
     * novo: os números saem do mesmo diagrama, lidos de outro jeito (US-4.3).
     */
    seqMap(): SequenceMap {
        return seqMap(
            this.state.seqMode,
            this.state.edges,
            this.state.nodes,
            this.index,
        );
    }

    /**
     * A legenda de agora, derivada inteira do desenho: nenhuma configuração do
     * usuário entra nela, e diagrama vazio devolve legenda vazia (US-5.1).
     */
    legendData(): LegendData {
        return legendData(
            this.state,
            this.index,
            this.linkIndex,
            this.sequenceModeOptions,
        );
    }

    /**
     * O diagrama inteiro em SVG, montado a partir do mesmo estado que a tela
     * desenha. As cores chegam resolvidas do tema ativo porque o arquivo não
     * tem as variáveis CSS do documento para consultar (US-9.1).
     */
    toSVG(palette: SvgPalette): string {
        return buildSVG({
            state: this.state,
            index: this.index,
            linkIndex: this.linkIndex,
            palette,
            modes: this.sequenceModeOptions,
            heights: this.heights,
        });
    }

    /**
     * A ordem de saída de cada bloco, que é o que os botões `‹ ›` reordenam.
     * Vale mesmo com a numeração desligada: a ordem existe no desenho, o modo
     * só decide se ela aparece (US-4.4).
     */
    outSeq(): SequenceMap {
        return outSeq(this.state.edges, this.state.nodes);
    }

    moveSeq(id: string, direction: number): boolean {
        return this.mutateEdge(() => moveSeq(this.state, id, direction));
    }

    /** Trocar de modo é ajuste de visualização: não empilha desfazer (US-4.3). */
    setSequenceMode(mode: SequenceMode): boolean {
        const next = sequenceModeOf(mode);

        if (this.state.seqMode === next) {
            return false;
        }

        this.state.seqMode = next;

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
