/**
 * A ordem explícita das conexões. Existe uma sequência única por diagrama, e o
 * número de cada aresta é o campo `order` dela — não a posição no array `edges`
 * nem uma derivação do desenho.
 *
 * Toda mutação de ordem sai por `densify()`: é o único ponto de saída, e é o
 * que garante que o conjunto dos `order` não nulos seja sempre exatamente
 * `{1, …, N}`, sem buraco e sem repetido.
 */

import { edgeById } from './nodes';
import type { Edge } from './types';

/** Quantas arestas estão na sequência. Aresta sem número não conta para `N`. */
export function numberedCount(edges: readonly Edge[]): number {
    return edges.filter((edge) => edge.order !== null).length;
}

/**
 * Reescreve os `order` não nulos como `1..N`, na ordem dos valores atuais e,
 * em caso de empate, na ordem do array. Varre `edges` inteiro, aresta órfã
 * inclusa: a órfã não é desenhada, mas continua ocupando o número dela.
 */
export function densify(edges: readonly Edge[]): boolean {
    const numbered = edges
        .flatMap((edge, position) =>
            edge.order === null ? [] : [{ edge, order: edge.order, position }],
        )
        .sort((a, b) => a.order - b.order || a.position - b.position);

    return numbered.reduce((changed, { edge }, index) => {
        if (edge.order === index + 1) {
            return changed;
        }

        edge.order = index + 1;

        return true;
    }, false);
}

/**
 * Põe a aresta na posição `k` da sequência: quem já ocupava `k` ou mais é
 * empurrado um número adiante, e o resto da sequência fecha o buraco que a
 * aresta deixou de onde estava. Valor fora da faixa cai na ponta mais próxima,
 * porque a densificação final não deixa número solto.
 */
export function setOrder(
    edges: readonly Edge[],
    id: string,
    k: number,
): boolean {
    const edge = edgeById(edges, id);

    if (!edge || !Number.isFinite(k)) {
        return false;
    }

    const before = orderSignature(edges);

    edge.order = null;
    densify(edges);

    const at = Math.trunc(k);

    for (const other of edges) {
        if (other !== edge && other.order !== null && other.order >= at) {
            other.order += 1;
        }
    }

    edge.order = at;
    densify(edges);

    return orderSignature(edges) !== before;
}

/** Tira a aresta da sequência e fecha o buraco que ela deixou. */
export function clearOrder(edges: readonly Edge[], id: string): boolean {
    const edge = edgeById(edges, id);

    if (!edge || edge.order === null) {
        return false;
    }

    edge.order = null;
    densify(edges);

    return true;
}

function orderSignature(edges: readonly Edge[]): string {
    return edges.map((edge) => `${edge.id}:${edge.order}`).join('|');
}
