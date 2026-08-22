import { NODE_WIDTH } from './geometry';
import type { Node, Point } from './types';

export const GRID_STEP = 4;

/**
 * A caixa de colisão do posicionamento automático: dois blocos se atrapalham
 * quando estão a menos de 122 px na horizontal **e** menos de 80 px na
 * vertical. É folga de leitura, não a caixa desenhada do bloco.
 */
export const COLLISION_X = NODE_WIDTH - 10;

export const COLLISION_Y = 80;

/**
 * Os deslocamentos que a varredura tenta, em ordem: o ponto pedido primeiro,
 * depois abaixo, à direita, à esquerda, acima e assim por diante.
 */
export const FREE_SPOT_OFFSETS: readonly Point[] = [
    [0, 0],
    [0, 110],
    [160, 0],
    [160, 110],
    [-160, 0],
    [0, -110],
    [-160, 110],
    [160, -110],
    [320, 0],
    [0, 220],
    [-320, 0],
    [320, 110],
];

/** A faixa do espalhamento aleatório, usada quando a varredura inteira falha. */
export const SCATTER_X = 260;

export const SCATTER_Y = 200;

export function snap(value: number): number {
    return Math.round(value / GRID_STEP) * GRID_STEP;
}

export function overlaps(
    x: number,
    y: number,
    nodes: readonly Node[],
): boolean {
    return nodes.some(
        (node) =>
            Math.abs(node.x - x) < COLLISION_X &&
            Math.abs(node.y - y) < COLLISION_Y,
    );
}

/**
 * O primeiro deslocamento livre a partir de `(x, y)`. Esgotada a varredura,
 * devolve um deslocamento aleatório dentro da faixa: o bloco novo pode cair
 * torto, mas nunca em cima de outro sem que o usuário perceba.
 */
export function freeSpot(
    x: number,
    y: number,
    nodes: readonly Node[],
    random: () => number = Math.random,
): Point {
    for (const [dx, dy] of FREE_SPOT_OFFSETS) {
        const candidate: Point = [x + dx, y + dy];

        if (!overlaps(candidate[0], candidate[1], nodes)) {
            return candidate;
        }
    }

    return [
        x + Math.round(random() * SCATTER_X - SCATTER_X / 2),
        y + Math.round(random() * SCATTER_Y - SCATTER_Y / 2),
    ];
}
