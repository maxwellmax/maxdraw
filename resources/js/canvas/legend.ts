/**
 * A legenda automática. Ela não tem estado próprio nem configuração: tudo o que
 * mostra é derivado do diagrama de agora — as categorias que estão desenhadas,
 * os tipos que as setas usam e o modo de numeração aceso (US-5.1).
 */

import type { ComponentIndex } from './catalog';
import { catalogCategories } from './catalog';
import { liveEdges } from './edges';
import type { LinkTypeIndex } from './links';
import { linkTypeOf, shortLinkName } from './links';
import type { SequenceModeOption } from './sequence';
import { seqMap, sequenceModeOf } from './sequence';
import type { Node, SequenceMode, SessionState } from './types';

/** Uma linha da seção *Blocos*: o quadradinho colorido, o nome e a contagem. */
export type LegendCategory = {
    slug: string;
    name: string;
    color: string;
    count: number;
};

/**
 * Uma linha da seção *Ligações*. `dash` é o padrão real do traço e `bidir` diz
 * se a amostra leva duas pontas; a cor não entra aqui de propósito — a amostra
 * é neutra, e só os quadradinhos de categoria têm cor (US-5.1).
 */
export type LegendLink = {
    slug: string;
    name: string;
    badge: string;
    dash: string | null;
    bidir: boolean;
    gloss: string;
};

/** A seção *Sequência*, com o nome e a glosa do modo aceso, vindos do catálogo. */
export type LegendSequence = {
    mode: SequenceMode;
    name: string;
    text: string;
};

/**
 * A linha que a legenda dá à seta que ainda não tem protocolo escolhido. Mora
 * aqui porque a tela e o arquivo exportado mostram a mesma legenda — não há uma
 * segunda (US-5.1, US-9.1).
 */
export const UNTYPED_NAME = 'sem tipo';

export const UNTYPED_GLOSS = 'clique na seta e escolha o protocolo';

export type LegendData = {
    categories: LegendCategory[];
    links: LegendLink[];
    /** Há seta desenhada sem tipo escolhido — a legenda ensina a escolher um. */
    untyped: boolean;
    sequence: LegendSequence | null;
    /** Nada desenhado: a legenda some por completo, sem moldura vazia. */
    empty: boolean;
};

/**
 * Quantos blocos cada categoria tem. Bloco de tipo fora do catálogo não conta:
 * a legenda ensina o vocabulário do catálogo, e ele não tem nome nem cor lá.
 */
function countByCategory(
    nodes: readonly Node[],
    index: ComponentIndex,
): Map<string, number> {
    const counts = new Map<string, number>();

    for (const node of nodes) {
        const category = index.get(node.type)?.category.slug;

        if (category) {
            counts.set(category, (counts.get(category) ?? 0) + 1);
        }
    }

    return counts;
}

/**
 * A seção de sequência só existe quando há número desenhado — o que descarta
 * tanto o modo desligado quanto o desenho que nenhum modo consegue numerar.
 */
function legendSequence(
    state: SessionState,
    index: ComponentIndex,
    modes: readonly SequenceModeOption[],
): LegendSequence | null {
    const numbers = seqMap(state.seqMode, state.edges, state.nodes, index);

    if (Object.keys(numbers).length === 0) {
        return null;
    }

    const mode = sequenceModeOf(state.seqMode);
    const option = modes.find((candidate) => candidate.slug === mode);

    return {
        mode,
        name: option?.name ?? '',
        text: option?.legend_text ?? '',
    };
}

/**
 * A legenda inteira, derivada do estado. Categorias e tipos saem na ordem do
 * catálogo, e só arestas válidas contam: a órfã que sobrou de um desfazer
 * parcial não é desenhada, então não tem o que explicar.
 */
export function legendData(
    state: SessionState,
    index: ComponentIndex,
    linkIndex: LinkTypeIndex,
    modes: readonly SequenceModeOption[] = [],
): LegendData {
    const counts = countByCategory(state.nodes, index);

    const categories = catalogCategories(index)
        .filter((category) => counts.has(category.slug))
        .map((category) => ({
            slug: category.slug,
            name: category.name,
            color: `var(${category.color_token})`,
            count: counts.get(category.slug) ?? 0,
        }));

    const drawn = liveEdges(state.edges, state.nodes);

    const links = [...linkIndex.values()]
        .filter((type) => drawn.some((edge) => edge.kind === type.slug))
        .map((type) => ({
            slug: type.slug,
            name: shortLinkName(type),
            badge: type.badge_label,
            dash: type.dash_array,
            bidir: type.is_bidirectional_default,
            gloss: type.gloss,
        }));

    const untyped = drawn.some(
        (edge) => linkTypeOf(linkIndex, edge.kind) === null,
    );

    return {
        categories,
        links,
        untyped,
        sequence: legendSequence(state, index, modes),
        empty: categories.length === 0 && links.length === 0 && !untyped,
    };
}

/** Os traços da amostra da legenda: a linha, a ponta e a segunda ponta. */
export type LineSample = {
    line: string;
    head: string;
    tail: string | null;
};

/** A caixa da amostra, no desenho do protótipo. */
export const SAMPLE_VIEWBOX = '0 0 38 12';

/**
 * A amostra do traço: uma linha com a ponta encostada à direita e, na mão
 * dupla, outra à esquerda — a linha encolhe para abrir espaço a ela. O padrão
 * do traço vem do `dash` da ligação e a cor, do `currentColor` de quem monta a
 * amostra, que na legenda é sempre neutro (US-5.1).
 */
export function lineSample(bidir: boolean): LineSample {
    return {
        line: bidir ? 'M9 6h24' : 'M5 6h28',
        head: 'M31 2.6 35.8 6 31 9.4Z',
        tail: bidir ? 'M7 2.6 2.2 6 7 9.4Z' : null,
    };
}
