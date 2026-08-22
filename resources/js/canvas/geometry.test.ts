import { describe, expect, it } from 'vitest';
import {
    bez,
    clipPt,
    CONTROL_MAX,
    CONTROL_MIN,
    head,
    NODE_HEIGHT,
    NODE_WIDTH,
    ptAt,
} from './geometry';
import type { Edge, Node } from './types';

function node(id: string, x: number, y: number): Node {
    return { id, type: 'api', label: id, x, y };
}

function edge(from: string, to: string): Edge {
    return {
        id: `${from}-${to}`,
        from,
        to,
        kind: null,
        label: '',
        dashed: false,
        bidir: false,
    };
}

/** O quanto o ponto se afasta do centro, em unidades de meia-caixa. */
function borderDistance(x: number, y: number, w: number, h: number): number {
    return Math.max(Math.abs(x) / (w / 2), Math.abs(y) / (h / 2));
}

describe('clipPt', () => {
    it('clipPt_lands_on_rectangle_border', () => {
        const targets: Array<[number, number]> = [
            [900, 0],
            [0, 900],
            [-900, 0],
            [0, -900],
            [600, 600],
            [-600, 400],
        ];

        for (const [tx, ty] of targets) {
            const [x, y] = clipPt(0, 0, NODE_WIDTH, NODE_HEIGHT, tx, ty, 0);

            expect(borderDistance(x, y, NODE_WIDTH, NODE_HEIGHT)).toBeCloseTo(
                1,
                10,
            );
        }
    });

    it('clipPt_returns_center_when_target_equals_center', () => {
        expect(clipPt(10, 20, NODE_WIDTH, NODE_HEIGHT, 10, 20, 7)).toEqual([
            10, 20,
        ]);
    });

    it('não estica até a borda quando o alvo está dentro do retângulo', () => {
        expect(clipPt(0, 0, NODE_WIDTH, NODE_HEIGHT, 12, 4, 7)).toEqual([
            12, 4,
        ]);
    });

    it('afasta o ponto do bloco na medida do pad', () => {
        const [x] = clipPt(0, 0, NODE_WIDTH, NODE_HEIGHT, 900, 0, 7);

        expect(x).toBe(NODE_WIDTH / 2 + 7);
    });
});

describe('bez', () => {
    it('bez_uses_horizontal_control_when_dx_dominates', () => {
        const nodes = [node('a', 0, 0), node('b', 600, 40)];
        const geometry = bez(edge('a', 'b'), nodes);

        expect(geometry).not.toBeNull();
        expect(geometry?.c1[1]).toBe(geometry?.y1);
        expect(geometry?.c2[1]).toBe(geometry?.y2);
        expect(geometry?.c1[0]).not.toBe(geometry?.x1);
    });

    it('bez_uses_vertical_control_when_dy_dominates', () => {
        const nodes = [node('a', 0, 0), node('b', 20, 600)];
        const geometry = bez(edge('a', 'b'), nodes);

        expect(geometry).not.toBeNull();
        expect(geometry?.c1[0]).toBe(geometry?.x1);
        expect(geometry?.c2[0]).toBe(geometry?.x2);
        expect(geometry?.c1[1]).not.toBe(geometry?.y1);
    });

    it('bez_clamps_control_between_26_and_110', () => {
        const close = bez(edge('a', 'b'), [
            node('a', 0, 0),
            node('b', 150, 0),
        ])!;
        const far = bez(edge('a', 'b'), [node('a', 0, 0), node('b', 1000, 0)])!;

        expect(close.c1[0] - close.x1).toBeCloseTo(CONTROL_MIN, 10);
        expect(far.c1[0] - far.x1).toBeCloseTo(CONTROL_MAX, 10);
    });

    it('recorta a origem com pad 7 e o destino com pad 10', () => {
        const geometry = bez(edge('a', 'b'), [
            node('a', 0, 0),
            node('b', 600, 0),
        ]);

        expect(geometry?.x1).toBe(NODE_WIDTH / 2 + (NODE_WIDTH / 2 + 7));
        expect(geometry?.x2).toBe(600 + NODE_WIDTH / 2 - (NODE_WIDTH / 2 + 10));
    });

    it('acompanha a altura real de um bloco de rótulo alto', () => {
        const nodes = [node('a', 0, 0), node('b', 0, 600)];
        const padrao = bez(edge('a', 'b'), nodes);
        const alto = bez(edge('a', 'b'), nodes, { a: 130 });

        expect(alto?.y1).toBeGreaterThan(padrao?.y1 ?? 0);
    });

    it('devolve nulo para laço e para bloco que não existe mais', () => {
        const nodes = [node('a', 0, 0)];

        expect(bez(edge('a', 'a'), nodes)).toBeNull();
        expect(bez(edge('a', 'sumiu'), nodes)).toBeNull();
    });
});

describe('ptAt', () => {
    it('ptAt_at_zero_and_one_matches_endpoints', () => {
        const geometry = bez(edge('a', 'b'), [
            node('a', 0, 0),
            node('b', 480, 260),
        ]);

        expect(ptAt(geometry!, 0)).toEqual([geometry!.x1, geometry!.y1]);
        expect(ptAt(geometry!, 1)).toEqual([geometry!.x2, geometry!.y2]);
    });

    it('devolve o ponto médio da curva em t = 0,5', () => {
        const geometry = bez(edge('a', 'b'), [
            node('a', 0, 0),
            node('b', 600, 0),
        ]);
        const [x, y] = ptAt(geometry!, 0.5);

        expect(x).toBeCloseTo((geometry!.x1 + geometry!.x2) / 2, 10);
        expect(y).toBeCloseTo(geometry!.y1, 10);
    });
});

describe('head', () => {
    it('head_points_along_the_tangent', () => {
        const horizontal = head(100, 50, 20, 50);

        expect(horizontal.tip).toEqual([100, 50]);
        expect(horizontal.left[0]).toBeCloseTo(90.5, 10);
        expect(horizontal.right[0]).toBeCloseTo(90.5, 10);
        expect(horizontal.left[1]).toBeCloseTo(46.1, 10);
        expect(horizontal.right[1]).toBeCloseTo(53.9, 10);

        const vertical = head(50, 100, 50, 20);
        const baseX = (vertical.left[0] + vertical.right[0]) / 2;
        const baseY = (vertical.left[1] + vertical.right[1]) / 2;

        expect(baseX).toBeCloseTo(50, 10);
        expect(baseY).toBeCloseTo(90.5, 10);
    });

    it('mantém o triângulo com 9,5 de comprimento e 3,9 de meia-largura', () => {
        const arrow = head(300, 120, 100, 260);
        const baseX = (arrow.left[0] + arrow.right[0]) / 2;
        const baseY = (arrow.left[1] + arrow.right[1]) / 2;
        const length = Math.hypot(300 - baseX, 120 - baseY);
        const width = Math.hypot(
            arrow.left[0] - arrow.right[0],
            arrow.left[1] - arrow.right[1],
        );

        expect(length).toBeCloseTo(9.5, 10);
        expect(width).toBeCloseTo(7.8, 10);
        expect(arrow.d.startsWith('M300 120L')).toBe(true);
        expect(arrow.d.endsWith('Z')).toBe(true);
    });
});
