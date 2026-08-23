/**
 * A ordem explícita das conexões. Existe uma sequência única por diagrama, e o
 * número de cada aresta é o campo `order` dela — não a posição no array `edges`
 * nem uma derivação do desenho.
 *
 * Toda mutação de ordem sai por `densify()`: é o único ponto de saída, e é o
 * que garante que o conjunto dos `order` não nulos seja sempre exatamente
 * `{1, …, N}`, sem buraco e sem repetido.
 */

import type { ComponentIndex } from './catalog';
import { isClientComponent } from './catalog';
import { liveEdges } from './edges';
import { edgeById } from './nodes';
import type { Edge, Node } from './types';

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

/**
 * O que o campo de ordem do painel comita. A aresta que já está na sequência
 * cabe em `N` posições; a que ainda não está tem uma a mais, porque entrar
 * estica a sequência. Campo vazio — ou qualquer coisa que não seja número —
 * devolve `null` e nada é comitado: quem tira da sequência é o botão, nunca o
 * campo (UI-03).
 */
export function clampOrderInput(
    value: string | number,
    numbered: number,
    isNumbered: boolean,
): number | null {
    const text = String(value).trim();
    const parsed = Number(text);

    if (text === '' || !Number.isFinite(parsed)) {
        return null;
    }

    const ceiling = Math.max(1, isNumbered ? numbered : numbered + 1);

    return Math.min(ceiling, Math.max(1, Math.trunc(parsed)));
}

/**
 * Numera o diagrama inteiro de uma vez: uma BFS determinística percorre as
 * arestas vivas e as reescreve de 1 a L na ordem em que as alcança,
 * sobrescrevendo qualquer `order` anterior — `null` inclusive (RF-10).
 *
 * A travessia segue somente `from → to`: `bidir` é bandeira de desenho e não
 * conta como aresta de entrada em `from` (RF-17). Cada aresta é visitada uma
 * única vez, o que faz um diagrama com ciclo terminar.
 *
 * A órfã não é visitada, porque não é desenhada e não há por onde alcançá-la:
 * ela é ranqueada depois de todas as vivas, preservando a ordem relativa entre
 * as órfãs numeradas, e a órfã sem número continua sem número (RF-16).
 */
export function autoNumber(
    edges: readonly Edge[],
    nodes: readonly Node[],
    index: ComponentIndex = new Map(),
): boolean {
    const before = orderSignature(edges);
    const walked = breadthFirst(edges, nodes, index);
    const visited = new Set(walked.map((edge) => edge.id));

    for (const [position, edge] of walked.entries()) {
        edge.order = position + 1;
    }

    const trailing = edges
        .flatMap((edge, position) =>
            visited.has(edge.id) || edge.order === null
                ? []
                : [{ edge, order: edge.order, position }],
        )
        .sort((a, b) => a.order - b.order || a.position - b.position);

    for (const [rank, { edge }] of trailing.entries()) {
        edge.order = walked.length + rank + 1;
    }

    densify(edges);

    return orderSignature(edges) !== before;
}

/**
 * As arestas vivas na ordem da travessia. A fila é um array com índice de
 * leitura, e a expansão de cada bloco sai do array `edges` — nenhum `Set` ou
 * `Map` é iterado por conta própria, que é o que mantém a BFS determinística.
 */
function breadthFirst(
    edges: readonly Edge[],
    nodes: readonly Node[],
    index: ComponentIndex,
): Edge[] {
    const live = liveEdges(edges, nodes);

    if (live.length === 0) {
        return [];
    }

    const outgoing = new Map<string, Edge[]>();
    const hasInput = new Set<string>();

    for (const edge of live) {
        const siblings = outgoing.get(edge.from);

        if (siblings) {
            siblings.push(edge);
        } else {
            outgoing.set(edge.from, [edge]);
        }

        hasInput.add(edge.to);
    }

    const walked: Edge[] = [];
    const seen = new Set<string>();

    for (const root of sequenceRoots(nodes, hasInput, index)) {
        if (seen.size === live.length) {
            break;
        }

        const queue = [root.id];

        for (let head = 0; head < queue.length; head++) {
            for (const edge of outgoing.get(queue[head]) ?? []) {
                if (seen.has(edge.id)) {
                    continue;
                }

                seen.add(edge.id);
                walked.push(edge);
                queue.push(edge.to);
            }
        }
    }

    return walked;
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

function orderSignature(edges: readonly Edge[]): string {
    return edges.map((edge) => `${edge.id}:${edge.order}`).join('|');
}
