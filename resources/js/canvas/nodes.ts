import type { Edge, Node, NodeHeights } from './types';

/**
 * A altura padrão de um bloco. A largura é fixa (`NODE_WIDTH`), a altura não:
 * um rótulo de duas linhas estica o bloco e a aresta acompanha.
 */
export const NODE_HEIGHT = 86;

export function nodeById(nodes: readonly Node[], id: string): Node | null {
    return nodes.find((node) => node.id === id) ?? null;
}

export function edgeById(edges: readonly Edge[], id: string): Edge | null {
    return edges.find((edge) => edge.id === id) ?? null;
}

export function nodeHeight(node: Node, heights: NodeHeights = {}): number {
    return heights[node.id] ?? NODE_HEIGHT;
}
