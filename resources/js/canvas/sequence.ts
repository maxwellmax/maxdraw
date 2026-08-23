/**
 * A numeração das setas. Ela é sempre derivada do desenho — nenhuma aresta
 * guarda número, e nenhum campo de ordem existe no estado: quem manda é a
 * posição da aresta no array `edges` (US-4.3, US-4.4).
 */

import type { ComponentIndex } from './catalog';
import { isClientComponent } from './catalog';
import { liveEdges } from './edges';
import type { Edge, Node, SequenceMode } from './types';

/** O número que a seta mostra: a posição e o total do conjunto a que pertence. */
export type SequenceNumber = {
    index: number;
    total: number;
};

/** O número de cada aresta, por id. Aresta sem número não entra no mapa. */
export type SequenceMap = Record<string, SequenceNumber>;

/**
 * Os modos como o servidor os entrega (`SequenceModeResource`). O cliente não
 * guarda os nomes deles, só a ordem em que o menu os mostra.
 */
export type SequenceModeOption = {
    id: number;
    slug: string;
    name: string;
    legend_text: string;
    position: number;
};

/** Uma linha do menu de numeração, já resolvida contra o catálogo. */
export type SequenceMenuItem = {
    mode: SequenceMode;
    name: string;
};

/**
 * A ordem do menu, que não é a `position` do catálogo: aquela é a do ciclo
 * (out → flow → off), esta é a da lista de `z2.png`, que abre por "sem
 * números". Slug fora dos três não vira opção.
 */
export const SEQUENCE_MENU: SequenceMode[] = ['off', 'out', 'flow'];

/** Modo ausente, nulo ou desconhecido cai no padrão do catálogo. */
export function sequenceModeOf(mode: string | null | undefined): SequenceMode {
    return mode === 'off' || mode === 'flow' ? mode : 'out';
}

export function sequenceMenu(
    options: readonly SequenceModeOption[],
): SequenceMenuItem[] {
    return SEQUENCE_MENU.flatMap((mode) => {
        const option = options.find((candidate) => candidate.slug === mode);

        return option ? [{ mode, name: option.name }] : [];
    });
}

/**
 * A ordem das saídas de cada bloco. Só numera quem tem escolha: bloco com uma
 * saída só não ganha número, porque não há o que ordenar. Aresta inválida não
 * conta nem para o índice nem para o total — ela sequer é desenhada.
 */
export function outSeq(
    edges: readonly Edge[],
    nodes: readonly Node[],
): SequenceMap {
    const live = liveEdges(edges, nodes);
    const outputs = new Map<string, number>();

    for (const edge of live) {
        outputs.set(edge.from, (outputs.get(edge.from) ?? 0) + 1);
    }

    const running = new Map<string, number>();
    const numbers: SequenceMap = {};

    for (const edge of live) {
        const total = outputs.get(edge.from) ?? 0;

        if (total < 2) {
            continue;
        }

        const index = (running.get(edge.from) ?? 0) + 1;

        running.set(edge.from, index);
        numbers[edge.id] = { index, total };
    }

    return numbers;
}

/**
 * A sequência do fluxo inteiro: uma busca em profundidade que segue a ordem de
 * saída de cada bloco e numera 1..N. Cada aresta é visitada uma única vez, o
 * que faz um diagrama com ciclo terminar; os nós restantes entram como raiz no
 * fim, para que nenhum componente desconexo fique sem número.
 */
export function flowSeq(
    edges: readonly Edge[],
    nodes: readonly Node[],
    index: ComponentIndex = new Map(),
): SequenceMap {
    const live = liveEdges(edges, nodes);

    if (live.length === 0) {
        return {};
    }

    const outgoing = new Map<string, Edge[]>();
    const hasInput = new Set<string>();

    for (const edge of live) {
        outgoing.set(edge.from, [...(outgoing.get(edge.from) ?? []), edge]);
        hasInput.add(edge.to);
    }

    const walked: string[] = [];
    const seen = new Set<string>();

    const walk = (id: string): void => {
        for (const edge of outgoing.get(id) ?? []) {
            if (seen.has(edge.id)) {
                continue;
            }

            seen.add(edge.id);
            walked.push(edge.id);
            walk(edge.to);
        }
    };

    for (const root of sequenceRoots(nodes, hasInput, index)) {
        if (seen.size === live.length) {
            break;
        }

        walk(root.id);
    }

    return Object.fromEntries(
        walked.map((id, position) => [
            id,
            { index: position + 1, total: walked.length },
        ]),
    );
}

/**
 * Por onde a travessia começa: primeiro o cliente sem nada entrando nele — o
 * fluxo de um sistema nasce em quem o usa —, depois as outras entradas do
 * diagrama e, por último, todos os nós, que é o que alcança quem só existe
 * dentro de um ciclo ou de um componente solto.
 */
function sequenceRoots(
    nodes: readonly Node[],
    hasInput: ReadonlySet<string>,
    index: ComponentIndex,
): Node[] {
    const entries = nodes.filter((node) => !hasInput.has(node.id));

    return [
        ...entries.filter((node) => isClientComponent(index, node.type)),
        ...entries.filter((node) => !isClientComponent(index, node.type)),
        ...nodes,
    ];
}

/** O mapa que a tela desenha. `off` não numera nada, e nem por isso apaga. */
export function seqMap(
    mode: string | null | undefined,
    edges: readonly Edge[],
    nodes: readonly Node[],
    index: ComponentIndex = new Map(),
): SequenceMap {
    const normalized = sequenceModeOf(mode);

    if (normalized === 'off') {
        return {};
    }

    return normalized === 'flow'
        ? flowSeq(edges, nodes, index)
        : outSeq(edges, nodes);
}
