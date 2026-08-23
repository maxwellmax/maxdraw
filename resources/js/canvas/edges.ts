import type { ComponentIndex } from './catalog';
import { colorOf } from './catalog';
import type { LinkTypeIndex } from './links';
import { badgeLabelOf, dashArrayOf, MANUAL_DASH_ARRAY } from './links';
import { nodeById } from './nodes';
import type { Edge, Node } from './types';

/**
 * O que a seta mostra no meio da curva: o selo do tipo e o rótulo livre, que
 * convivem lado a lado. Sem os dois o chip fica sem borda e sem fundo — não há
 * o que emoldurar (US-4.2).
 */
export type EdgeChip = {
    badge: string;
    label: string;
    bare: boolean;
};

/** Uma ligação sai de um bloco e chega em outro, e os dois precisam existir. */
export function edgeOk(edge: Edge, nodes: readonly Node[]): boolean {
    return (
        edge.from !== edge.to &&
        nodeById(nodes, edge.from) !== null &&
        nodeById(nodes, edge.to) !== null
    );
}

/**
 * As arestas que o palco desenha. Apagar um bloco leva junto as arestas dele,
 * mas um desfazer parcial pode deixar uma órfã para trás: ela é ignorada no
 * desenho e continua no estado, para o refazer devolvê-la inteira.
 */
export function liveEdges(
    edges: readonly Edge[],
    nodes: readonly Node[],
): Edge[] {
    return edges.filter((edge) => edgeOk(edge, nodes));
}

/**
 * O traço da seta. O tipo dá o padrão e a bandeira manual decide: escolher
 * `event` liga o tracejado, desligá-lo depois devolve o traço contínuo sem
 * tirar o tipo da seta, e tracejar um `http` usa o padrão manual (US-4.1).
 */
export function dashOf(index: LinkTypeIndex, edge: Edge): string | null {
    if (!edge.dashed) {
        return null;
    }

    return dashArrayOf(index, edge.kind) ?? MANUAL_DASH_ARRAY;
}

/**
 * A cor da seta é a da categoria do bloco de **origem**. Nenhum tipo de ligação
 * traz cor própria: os nove tipos convivem dentro das seis cores de categoria.
 */
export function edgeColor(
    index: ComponentIndex,
    edge: Edge,
    nodes: readonly Node[],
): string {
    return colorOf(index, nodeById(nodes, edge.from)?.type ?? '');
}

export function edgeChip(index: LinkTypeIndex, edge: Edge): EdgeChip {
    const badge = badgeLabelOf(index, edge.kind);

    return {
        badge,
        label: edge.label,
        bare: badge === '' && edge.label === '',
    };
}
