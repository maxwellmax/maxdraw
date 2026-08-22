import type { CatalogComponent } from './catalog';
import { NODE_HEIGHT, NODE_WIDTH } from './geometry';
import { normalizeLabel } from './labels';
import { freeSpot, snap } from './layout';
import { MAX_EDGES, MAX_NODES } from './limits';
import { edgeById, nodeById, nodeHeight } from './nodes';
import type {
    DiagramSnapshot,
    Edge,
    MutationResult,
    Node,
    NodeHeights,
    Point,
    SessionState,
} from './types';

export function uid(): string {
    return Math.random().toString(36).slice(2, 9);
}

/**
 * Insere um bloco perto de `(x, y)`, desviando do que já está desenhado. Acima
 * de 200 nós a inserção é recusada com o motivo, para o chamador avisar o
 * usuário em vez de engolir o clique (US-3.1).
 */
export function addNode(
    state: SessionState,
    component: CatalogComponent,
    at: Point,
): MutationResult<Node> {
    if (state.nodes.length >= MAX_NODES) {
        return { ok: false, reason: 'nodeLimitReached' };
    }

    const [x, y] = freeSpot(snap(at[0]), snap(at[1]), state.nodes);

    const node: Node = {
        id: uid(),
        type: component.slug,
        label: component.short_name,
        x: snap(x),
        y: snap(y),
    };

    state.nodes.push(node);

    return { ok: true, value: node };
}

/**
 * Liga dois blocos. Recusa laço, aresta repetida e o limite de 400 — a aresta
 * nasce sem tipo, que é o que a barra flutuante escolhe depois (US-4.1).
 */
export function addEdge(
    state: SessionState,
    from: string,
    to: string,
): MutationResult<Edge> {
    if (state.edges.length >= MAX_EDGES) {
        return { ok: false, reason: 'edgeLimitReached' };
    }

    if (
        from === to ||
        !nodeById(state.nodes, from) ||
        !nodeById(state.nodes, to)
    ) {
        return { ok: false, reason: 'invalidLink' };
    }

    const existing = state.edges.find(
        (edge) => edge.from === from && edge.to === to,
    );

    if (existing) {
        return { ok: true, value: existing };
    }

    const edge: Edge = {
        id: uid(),
        from,
        to,
        kind: null,
        label: '',
        dashed: false,
        bidir: false,
    };

    state.edges.push(edge);

    return { ok: true, value: edge };
}

/**
 * Move o bloco para a grade de 4 px. Devolve `false` quando o destino é a
 * posição em que o bloco já estava — é assim que um arrasto que não moveu nada
 * deixa de empilhar desfazer.
 */
export function moveNode(
    state: SessionState,
    id: string,
    x: number,
    y: number,
): boolean {
    const node = nodeById(state.nodes, id);

    if (!node) {
        return false;
    }

    const nextX = snap(x);
    const nextY = snap(y);

    if (node.x === nextX && node.y === nextY) {
        return false;
    }

    node.x = nextX;
    node.y = nextY;

    return true;
}

export function renameNode(
    state: SessionState,
    id: string,
    label: string,
    fallback: string,
): boolean {
    const node = nodeById(state.nodes, id);

    if (!node) {
        return false;
    }

    const next = normalizeLabel(label, fallback);

    if (node.label === next) {
        return false;
    }

    node.label = next;

    return true;
}

/** Apagar um bloco apaga junto tudo que entrava nele e tudo que saía dele. */
export function removeNode(state: SessionState, id: string): boolean {
    if (!nodeById(state.nodes, id)) {
        return false;
    }

    state.nodes = state.nodes.filter((node) => node.id !== id);
    state.edges = state.edges.filter(
        (edge) => edge.from !== id && edge.to !== id,
    );

    return true;
}

export function removeEdge(state: SessionState, id: string): boolean {
    if (!edgeById(state.edges, id)) {
        return false;
    }

    state.edges = state.edges.filter((edge) => edge.id !== id);

    return true;
}

/** O retrato do diagrama, desacoplado do estado vivo. */
export function snapshot(state: SessionState): DiagramSnapshot {
    return {
        nodes: state.nodes.map((node) => ({ ...node })),
        edges: state.edges.map((edge) => ({ ...edge })),
    };
}

export function restore(state: SessionState, diagram: DiagramSnapshot): void {
    state.nodes = diagram.nodes.map((node) => ({ ...node }));
    state.edges = diagram.edges.map((edge) => ({ ...edge }));
}

export type Bounds = {
    x0: number;
    y0: number;
    x1: number;
    y1: number;
};

export function diagramBounds(
    nodes: readonly Node[],
    heights: NodeHeights = {},
): Bounds | null {
    if (nodes.length === 0) {
        return null;
    }

    return nodes.reduce<Bounds>(
        (bounds, node) => ({
            x0: Math.min(bounds.x0, node.x),
            y0: Math.min(bounds.y0, node.y),
            x1: Math.max(bounds.x1, node.x + NODE_WIDTH),
            y1: Math.max(bounds.y1, node.y + nodeHeight(node, heights)),
        }),
        {
            x0: Infinity,
            y0: Infinity,
            x1: -Infinity,
            y1: -Infinity,
        },
    );
}

export { NODE_HEIGHT, NODE_WIDTH };
