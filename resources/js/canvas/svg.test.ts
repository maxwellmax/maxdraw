import { describe, expect, it } from 'vitest';
import { indexComponents } from './catalog';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    paletteFixture,
    sequenceModesFixture,
    stateFixture,
} from './fixtures';
import { legendData } from './legend';
import { indexLinkTypes } from './links';
import { LEGEND_GAP, SVG_PADDING, svgLegend, wrapLabel } from './svg';
import type { Edge, Node, SequenceMode, SessionState } from './types';

const palette = paletteFixture();

function engineWith(state: SessionState): CanvasEngine {
    return new CanvasEngine(
        state,
        catalogFixture(),
        linkTypesFixture(),
        sequenceModesFixture(),
    );
}

function svgOf(
    nodes: Node[] = [],
    edges: Edge[] = [],
    mode: SequenceMode = 'out',
): string {
    const state = stateFixture(nodes, edges);

    state.seqMode = mode;

    return engineWith(state).toSVG(palette);
}

function legendOf(
    nodes: Node[] = [],
    edges: Edge[] = [],
    mode: SequenceMode = 'out',
) {
    const state = stateFixture(nodes, edges);

    state.seqMode = mode;

    return legendData(
        state,
        indexComponents(catalogFixture()),
        indexLinkTypes(linkTypesFixture()),
        sequenceModesFixture(),
    );
}

function typed(id: string, from: string, to: string, kind: string): Edge {
    return { ...edgeFixture(id, from, to), kind };
}

/** Os atributos de um mesmo nome, na ordem em que aparecem na string. */
function attributes(markup: string, name: string): string[] {
    return [...markup.matchAll(new RegExp(`${name}="([^"]*)"`, 'g'))].map(
        (match) => match[1],
    );
}

/** Os retângulos de chip, que são os únicos com 18 de altura e canto 4. */
function chipRects(markup: string): { x: number; width: number }[] {
    return [
        ...markup.matchAll(
            /<rect x="(-?[\d.]+)" y="-?[\d.]+" width="([\d.]+)" height="18" rx="4"/g,
        ),
    ].map((match) => ({ x: Number(match[1]), width: Number(match[2]) }));
}

/** Os círculos de número da seta, que são os únicos de raio 7.6. */
function seqDots(markup: string): number[] {
    return [
        ...markup.matchAll(/<circle cx="(-?[\d.]+)" cy="-?[\d.]+" r="7\.6"/g),
    ].map((match) => Number(match[1]));
}

/** As pontas de seta: os únicos triângulos preenchidos do corpo do desenho. */
function arrowHeads(markup: string, color: string): number {
    return markup.split(`Z" fill="${color}" stroke="none"/>`).length - 1;
}

function viewBox(markup: string): number[] {
    return attributes(markup, 'viewBox')[0].split(' ').map(Number);
}

describe('buildSVG', () => {
    it('svg_chips_are_emitted_after_node_groups', () => {
        const markup = svgOf(
            [
                nodeFixture('a', 0, 0, 'browser'),
                nodeFixture('b', 300, 0, 'api'),
            ],
            [{ ...typed('e1', 'a', 'b', 'http'), label: 'login' }],
        );

        const wire = markup.indexOf('<path d="M');
        const group = markup.indexOf('<g transform="translate(');
        const chip = markup.indexOf('login');

        expect(wire).toBeGreaterThan(-1);
        expect(group).toBeGreaterThan(wire);
        expect(chip).toBeGreaterThan(group);
    });

    it('svg_viewbox_covers_all_nodes_with_padding', () => {
        const nodes = [
            nodeFixture('a', 120, 60, 'browser'),
            nodeFixture('b', 420, 260, 'api'),
        ];
        const markup = svgOf(nodes);

        // Os blocos ocupam 120..552 na horizontal e 60..346 na vertical.
        const legend = svgLegend(
            legendOf(nodes),
            120,
            346 + LEGEND_GAP,
            palette,
        );
        const box = viewBox(markup);

        expect(box[0]).toBe(120 - SVG_PADDING);
        expect(box[1]).toBe(60 - SVG_PADDING);
        expect(box[2]).toBe(432 + SVG_PADDING * 2);
        expect(box[3]).toBe(286 + legend.height + LEGEND_GAP + SVG_PADDING * 2);

        expect(attributes(markup, 'width')[0]).toBe(String(box[2]));
        expect(attributes(markup, 'height')[0]).toBe(String(box[3]));
    });

    it('svg_width_accommodates_a_wider_legend', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 150, 0, 'api'),
        ];
        const edges = [typed('e1', 'a', 'b', 'retry')];
        const legend = svgLegend(
            legendOf(nodes, edges),
            0,
            LEGEND_GAP + 86,
            palette,
        );

        // O desenho tem 282 px de largura; a legenda é mais larga que isso.
        expect(legend.width).toBeGreaterThan(282);
        expect(viewBox(svgOf(nodes, edges))[2]).toBe(
            legend.width + SVG_PADDING * 2,
        );

        const narrow = svgOf([
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 900, 0, 'api'),
        ]);

        expect(viewBox(narrow)[2]).toBe(1032 + SVG_PADDING * 2);
    });

    it('svg_edge_stroke_matches_source_category_color', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'sql'),
        ];

        expect(svgOf(nodes, [typed('e1', 'a', 'b', 'http')])).toContain(
            `stroke="${palette['--c-client']}" stroke-width="1.8"`,
        );

        // Inverter a seta recolore o traço: a cor é sempre a da origem.
        expect(svgOf(nodes, [typed('e1', 'b', 'a', 'query')])).toContain(
            `stroke="${palette['--c-data']}" stroke-width="1.8"`,
        );
    });

    it('svg_bidirectional_edge_has_two_arrowheads', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'api'),
        ];
        const color = palette['--c-client'];

        const single = svgOf(nodes, [typed('e1', 'a', 'b', 'http')]);
        const double = svgOf(nodes, [
            { ...typed('e1', 'a', 'b', 'ws'), bidir: true },
        ]);

        expect(arrowHeads(single, color)).toBe(1);
        expect(arrowHeads(double, color)).toBe(2);
    });

    it('svg_chip_with_number_widens_by_20px', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'api'),
            nodeFixture('c', 300, 300, 'sql'),
        ];
        const first = { ...typed('e1', 'a', 'b', 'http'), label: 'login' };

        // A segunda saída do mesmo bloco é o que faz o modo `out` numerar.
        const plain = chipRects(svgOf(nodes, [first]));
        const numbered = chipRects(
            svgOf(nodes, [first, edgeFixture('e2', 'a', 'c')]),
        );

        expect(plain).toHaveLength(1);
        expect(numbered).toHaveLength(1);
        expect(numbered[0].width - plain[0].width).toBe(20);

        // O círculo fica à esquerda do retângulo, não no meio da curva.
        const numberedMarkup = svgOf(nodes, [
            first,
            edgeFixture('e2', 'a', 'c'),
        ]);

        expect(seqDots(numberedMarkup)[0]).toBeCloseTo(numbered[0].x + 11, 5);
        expect(numberedMarkup).toContain('>1</text>');
    });

    it('svg_bare_edge_with_number_renders_only_the_circle', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'api'),
            nodeFixture('c', 300, 300, 'sql'),
        ];
        const markup = svgOf(nodes, [
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('e2', 'a', 'c'),
        ]);

        // Sem selo e sem rótulo não há moldura: sobra o círculo do número.
        expect(chipRects(markup)).toEqual([]);
        expect(seqDots(markup)).toHaveLength(2);
        expect(markup).toContain('>1</text>');
        expect(markup).toContain('>2</text>');

        // Sem número e sem texto não sobra nem o círculo.
        const silent = svgOf(nodes.slice(0, 2), [edgeFixture('e1', 'a', 'b')]);

        expect(seqDots(silent)).toEqual([]);
    });

    it('svg_labels_wrap_to_at_most_three_lines', () => {
        expect(
            wrapLabel('Serviço de autenticação e autorização central'),
        ).toEqual(['Serviço de', 'autenticação e', 'autorização']);

        expect(wrapLabel('API')).toEqual(['API']);

        const node = nodeFixture('a', 0, 0, 'api');

        node.label = 'Serviço de autenticação e autorização central';

        const group = svgOf([node]);

        expect(
            attributes(group, 'font-size').filter((size) => size === '12.5'),
        ).toHaveLength(3);
    });

    it('resolve as cores do tema em vez de deixar variáveis CSS no arquivo', () => {
        const markup = svgOf(
            [
                nodeFixture('a', 0, 0, 'browser'),
                nodeFixture('b', 300, 0, 'sql'),
            ],
            [{ ...typed('e1', 'a', 'b', 'query'), label: 'SELECT' }],
        );

        expect(markup).not.toContain('var(--');
        expect(markup).toContain(`fill="${palette['--paper']}"`);
        expect(markup).toContain(`stroke="${palette['--line-2']}"`);
    });

    it('escapa o texto do usuário e o tracejado do tipo', () => {
        const node = nodeFixture('a', 0, 0, 'api');

        node.label = 'A & B <ok>';

        const markup = svgOf(
            [node, nodeFixture('b', 300, 0, 'sql')],
            [{ ...typed('e1', 'a', 'b', 'retry'), label: '"3x"' }],
        );

        expect(markup).toContain('A &amp; B &lt;ok&gt;');
        expect(markup).toContain('&quot;3x&quot;');
        expect(markup).toContain('stroke-dasharray="2 4.5"');
    });

    it('não desenha aresta órfã nem exporta diagrama vazio', () => {
        expect(svgOf()).toBe('');

        const markup = svgOf(
            [nodeFixture('a', 0, 0, 'browser')],
            [typed('órfã', 'a', 'sumiu', 'http')],
        );

        expect(markup).not.toContain('HTTP');
    });
});

describe('svgLegend', () => {
    it('svg_legend_matches_screen_legend_content', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'api'),
            nodeFixture('c', 300, 300, 'sql'),
        ];
        const edges = [
            typed('e1', 'a', 'b', 'http'),
            edgeFixture('e2', 'b', 'c'),
            typed('e3', 'b', 'a', 'event'),
        ];
        const data = legendOf(nodes, edges, 'flow');

        // Abaixo do desenho: os blocos terminam em 386, e a legenda vem 30 depois.
        const legend = svgLegend(data, 0, 386 + LEGEND_GAP, palette);

        expect(legend.markup).toContain('LEGENDA');
        expect(legend.markup).toContain('BLOCOS');
        expect(legend.markup).toContain('LIGAÇÕES');

        for (const category of data.categories) {
            expect(legend.markup).toContain(category.name);
            expect(legend.markup).toContain(`>${category.count}</tspan>`);
        }

        for (const link of data.links) {
            expect(legend.markup).toContain(`>${link.badge}</text>`);
            expect(legend.markup).toContain(`>${link.name}</text>`);
            expect(legend.markup).toContain(`>${link.gloss}</text>`);
        }

        expect(legend.markup).toContain('>sem tipo</text>');
        expect(legend.markup).toContain(
            '>clique na seta e escolha o protocolo</text>',
        );
        expect(legend.markup).toContain(`>${data.sequence?.name}</text>`);
        expect(legend.markup).toContain(`>${data.sequence?.text}</text>`);

        // A do arquivo é a mesma legenda da tela, colada abaixo do desenho.
        expect(svgOf(nodes, edges, 'flow')).toContain(legend.markup);
    });

    it('svg_legend_samples_are_neutral_colored', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 300, 0, 'sql'),
        ];
        const legend = svgLegend(
            legendOf(nodes, [typed('e1', 'a', 'b', 'event')]),
            0,
            0,
            palette,
        );

        const muted = palette['--ink-3'];

        expect(attributes(legend.markup, 'stroke')).toEqual([muted]);

        const swatches = [
            ...legend.markup.matchAll(
                /width="8" height="8" rx="2" fill="([^"]+)"/g,
            ),
        ].map((match) => match[1]);

        expect(swatches).toEqual([palette['--c-client'], palette['--c-data']]);

        // Fora dos quadradinhos, a legenda inteira é neutra.
        const rest = attributes(legend.markup, 'fill').filter(
            (fill) => !swatches.includes(fill),
        );

        expect([...new Set(rest)].sort()).toEqual(
            [muted, palette['--ink'], palette['--ink-2'], 'none'].sort(),
        );

        // O padrão do traço continua sendo o do tipo, só a cor é que não.
        expect(legend.markup).toContain('stroke-dasharray="5 4.5"');
    });

    it('svg_legend_is_absent_for_empty_diagram', () => {
        expect(svgLegend(legendOf(), 0, 0, palette)).toEqual({
            width: 0,
            height: 0,
            markup: '',
        });

        // Bloco de tipo fora do catálogo: há desenho, mas nada a explicar.
        const stranger = svgOf([nodeFixture('a', 0, 0, 'inexistente')]);

        expect(stranger).not.toContain('LEGENDA');
        expect(viewBox(stranger)).toEqual([-40, -40, 212, 166]);
    });

    it('svg_legend_dimensions_feed_the_viewbox', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 600, 0, 'sql'),
        ];
        const edges = [typed('e1', 'a', 'b', 'query')];
        const legend = svgLegend(
            legendOf(nodes, edges),
            0,
            86 + LEGEND_GAP,
            palette,
        );

        const box = viewBox(svgOf(nodes, edges));

        expect(legend.height).toBeGreaterThan(0);
        expect(box[2]).toBe(Math.max(732, legend.width) + SVG_PADDING * 2);
        expect(box[3]).toBe(86 + legend.height + LEGEND_GAP + SVG_PADDING * 2);
    });
});
