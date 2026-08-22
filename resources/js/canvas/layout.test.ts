import { describe, expect, it } from 'vitest';
import {
    COLLISION_X,
    COLLISION_Y,
    FREE_SPOT_OFFSETS,
    freeSpot,
    overlaps,
    SCATTER_X,
    SCATTER_Y,
    snap,
} from './layout';
import type { Node } from './types';

function node(id: string, x: number, y: number): Node {
    return { id, type: 'api', label: id, x, y };
}

/** Um bloco em cada deslocamento da varredura: nenhum candidato sobra livre. */
function everyOffsetTaken(x: number, y: number): Node[] {
    return FREE_SPOT_OFFSETS.map(([dx, dy], index) =>
        node(`n${index}`, x + dx, y + dy),
    );
}

describe('snap', () => {
    it('snap_rounds_to_multiples_of_four', () => {
        expect(snap(0)).toBe(0);
        expect(snap(1)).toBe(0);
        expect(snap(2)).toBe(4);
        expect(snap(3)).toBe(4);
        expect(snap(101)).toBe(100);
        expect(snap(-101)).toBe(-100);
        expect(snap(-102)).toBe(-100);

        for (const value of [7.4, 118.9, -33.3, 1000.1]) {
            expect(Math.abs(snap(value) % 4)).toBe(0);
        }
    });
});

describe('freeSpot', () => {
    it('freeSpot_avoids_overlap_with_existing_nodes', () => {
        const nodes = [node('a', 400, 300), node('b', 400, 410)];
        const [x, y] = freeSpot(400, 300, nodes);

        expect(overlaps(x, y, nodes)).toBe(false);
    });

    it('freeSpot_returns_first_free_offset_in_order', () => {
        expect(freeSpot(400, 300, [])).toEqual([400, 300]);

        expect(freeSpot(400, 300, [node('a', 400, 300)])).toEqual([400, 410]);

        expect(
            freeSpot(400, 300, [node('a', 400, 300), node('b', 400, 410)]),
        ).toEqual([560, 300]);

        expect(
            freeSpot(400, 300, [
                node('a', 400, 300),
                node('b', 400, 410),
                node('c', 560, 300),
            ]),
        ).toEqual([560, 410]);
    });

    it('considera livre o que estiver fora da caixa de colisão', () => {
        expect(overlaps(0, 0, [node('a', COLLISION_X, 0)])).toBe(false);
        expect(overlaps(0, 0, [node('a', COLLISION_X - 1, 0)])).toBe(true);
        expect(overlaps(0, 0, [node('a', 0, COLLISION_Y)])).toBe(false);
        expect(overlaps(0, 0, [node('a', 0, COLLISION_Y - 1)])).toBe(true);
        expect(overlaps(0, 0, [node('a', COLLISION_X - 1, COLLISION_Y)])).toBe(
            false,
        );
    });

    it('espalha dentro da faixa quando a varredura inteira está ocupada', () => {
        const nodes = everyOffsetTaken(400, 300);

        expect(freeSpot(400, 300, nodes, () => 0)).toEqual([
            400 - SCATTER_X / 2,
            300 - SCATTER_Y / 2,
        ]);
        expect(freeSpot(400, 300, nodes, () => 1)).toEqual([
            400 + SCATTER_X / 2,
            300 + SCATTER_Y / 2,
        ]);
        expect(freeSpot(400, 300, nodes, () => 0.5)).toEqual([400, 300]);
    });
});
