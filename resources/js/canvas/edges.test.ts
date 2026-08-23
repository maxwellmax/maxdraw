import { describe, expect, it } from 'vitest';
import { indexComponents } from './catalog';
import { edgeChip, edgeColor, edgeOk, liveEdges } from './edges';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    stateFixture,
} from './fixtures';
import { dashSample, indexLinkTypes, MANUAL_DASH_ARRAY } from './links';
import type { Edge, SessionState } from './types';

/** O traço de cada um dos nove tipos, como o `LinkTypeSeeder` os grava. */
const DASH_BY_KIND: Record<string, string | null> = {
    http: null,
    grpc: null,
    ws: null,
    event: '5 4.5',
    query: null,
    cache: null,
    repl: '5 4.5',
    batch: '5 4.5',
    retry: '2 4.5',
};

const LINKS = indexLinkTypes(linkTypesFixture());

const COMPONENTS = indexComponents(catalogFixture());

function engineWith(state: SessionState): CanvasEngine {
    return new CanvasEngine(state, catalogFixture(), linkTypesFixture());
}

/** Duas pontas ligadas e um tipo escolhido pelo caminho real, o do menu. */
function typedEdge(kind: string | null): { engine: CanvasEngine; edge: Edge } {
    const engine = engineWith(
        stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [edgeFixture('e1', 'a', 'b')],
        ),
    );

    engine.setEdgeKind('e1', kind);

    return { engine, edge: engine.edge('e1')! };
}

describe('edgeOk', () => {
    it('edgeOk_rejects_self_loops_and_dangling_edges', () => {
        const nodes = [nodeFixture('a'), nodeFixture('b', 400)];

        expect(edgeOk(edgeFixture('e1', 'a', 'b'), nodes)).toBe(true);
        expect(edgeOk(edgeFixture('e2', 'a', 'a'), nodes)).toBe(false);
        expect(edgeOk(edgeFixture('e3', 'a', 'sumiu'), nodes)).toBe(false);
        expect(edgeOk(edgeFixture('e4', 'sumiu', 'b'), nodes)).toBe(false);
    });

    it('ignora a aresta órfã no desenho sem tirá-la do estado', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [
                edgeFixture('e1', 'a', 'b'),
                edgeFixture('e2', 'a', 'apagado'),
                edgeFixture('e3', 'a', 'a'),
            ],
        );

        expect(
            liveEdges(state.edges, state.nodes).map((edge) => edge.id),
        ).toEqual(['e1']);
        expect(state.edges).toHaveLength(3);
    });
});

describe('traço', () => {
    it('dashOf_matches_each_of_the_nine_types', () => {
        for (const [kind, dash] of Object.entries(DASH_BY_KIND)) {
            const { engine, edge } = typedEdge(kind);

            expect(engine.dashOf(edge)).toBe(dash);
        }
    });

    it('manual_dashed_overrides_type_default', () => {
        const solid = typedEdge('http');

        solid.engine.toggleEdgeFlag('e1', 'dashed');

        expect(solid.engine.dashOf(solid.edge)).toBe(MANUAL_DASH_ARRAY);

        const dashed = typedEdge('event');

        dashed.engine.toggleEdgeFlag('e1', 'dashed');

        expect(dashed.engine.dashOf(dashed.edge)).toBeNull();
        expect(dashed.edge.kind).toBe('event');
    });

    it('devolve o traço contínuo quando a seta perde o tipo', () => {
        const { engine, edge } = typedEdge('retry');

        expect(engine.dashOf(edge)).toBe('2 4.5');

        engine.setEdgeKind('e1', null);

        expect(engine.dashOf(edge)).toBeNull();
        expect(engine.edgeChip(edge).badge).toBe('');
    });

    it('desenha a amostra do menu com o padrão real de cada tipo', () => {
        expect(dashSample(null)).toBe('currentColor');
        expect(dashSample('5 4.5')).toBe(
            'repeating-linear-gradient(90deg, currentColor 0 5px, transparent 5px 9.5px)',
        );
        expect(dashSample('2 4.5')).toBe(
            'repeating-linear-gradient(90deg, currentColor 0 2px, transparent 2px 6.5px)',
        );
    });
});

describe('cor', () => {
    it('edgeColor_follows_source_category', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'api'),
            nodeFixture('b', 400, 0, 'sql'),
        ];

        expect(edgeColor(COMPONENTS, edgeFixture('e1', 'a', 'b'), nodes)).toBe(
            'var(--c-compute)',
        );
        expect(edgeColor(COMPONENTS, edgeFixture('e2', 'b', 'a'), nodes)).toBe(
            'var(--c-data)',
        );
    });

    it('cai no cinza de texto quando a origem sumiu do diagrama', () => {
        expect(edgeColor(COMPONENTS, edgeFixture('e1', 'x', 'y'), [])).toBe(
            'var(--ink-3)',
        );
    });

    it('no_link_type_introduces_a_new_color', () => {
        const categoryColors = catalogFixture().map(
            (category) => `var(${category.color_token})`,
        );
        const engine = engineWith(
            stateFixture(
                [
                    nodeFixture('a', 0, 0, 'api'),
                    nodeFixture('b', 400, 0, 'sql'),
                ],
                [edgeFixture('e1', 'a', 'b')],
            ),
        );

        expect(categoryColors).toHaveLength(6);

        const used = new Set<string>();

        for (const kind of [...Object.keys(DASH_BY_KIND), null]) {
            engine.setEdgeKind('e1', kind);
            used.add(engine.edgeColor(engine.edge('e1')!));
        }

        expect([...used]).toEqual(['var(--c-compute)']);

        for (const color of used) {
            expect(categoryColors).toContain(color);
        }
    });
});

describe('chip', () => {
    it('bare_edge_renders_no_chip_background', () => {
        const edge = edgeFixture('e1', 'a', 'b');

        expect(edgeChip(LINKS, edge)).toEqual({
            badge: '',
            label: '',
            bare: true,
        });
    });

    it('põe o selo e o rótulo lado a lado, e o chip ganha moldura', () => {
        const edge = { ...edgeFixture('e1', 'a', 'b'), kind: 'event' };

        expect(edgeChip(LINKS, edge)).toEqual({
            badge: 'async',
            label: '',
            bare: false,
        });
        expect(edgeChip(LINKS, { ...edge, label: 'GET /feed' })).toEqual({
            badge: 'async',
            label: 'GET /feed',
            bare: false,
        });
        expect(
            edgeChip(LINKS, { ...edge, kind: null, label: 'GET /feed' }),
        ).toEqual({ badge: '', label: 'GET /feed', bare: false });
    });
});
