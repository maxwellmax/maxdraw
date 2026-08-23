/**
 * A exportação em SVG. O arquivo é o mesmo diagrama que a tela desenha, montado
 * a partir do mesmo estado — geometria, cores e legenda saem dos módulos que já
 * governam a prancheta, não de uma segunda régua (US-9.1).
 *
 * O que muda em relação à tela é só o alvo: aqui as cores chegam já resolvidas
 * do tema ativo, porque o arquivo baixado não tem as variáveis CSS do documento
 * para consultar.
 */

import type { ComponentIndex } from './catalog';
import { colorOf, shortNameOf } from './catalog';
import { diagramBounds } from './diagram';
import type { EdgeChip } from './edges';
import { dashOf, edgeChip, edgeColor } from './edges';
import { bez, head, NODE_WIDTH, ptAt } from './geometry';
import { CANVAS_ICONS, hasCanvasIcon } from './icons';
import type { LegendData } from './legend';
import {
    legendData,
    ORDER_GLOSS,
    ORDER_NAME,
    UNTYPED_GLOSS,
    UNTYPED_NAME,
} from './legend';
import type { LinkTypeIndex } from './links';
import { nodeHeight } from './nodes';
import type { EdgeGeometry, Node, NodeHeights, SessionState } from './types';

/** As cores do tema ativo, por token. É o que o `useTheme` relê a cada troca. */
export type SvgPalette = Readonly<Record<string, string>>;

/** A margem em volta do desenho, em unidades de mundo. */
export const SVG_PADDING = 40;

/** O vão entre a base do desenho e o topo do bloco da legenda. */
export const LEGEND_GAP = 30;

/** Quantos caracteres cabem numa linha do rótulo do bloco. */
export const LABEL_WRAP_CHARS = 18;

export const MAX_LABEL_LINES = 3;

/** A altura do pill do número da conexão. É a mesma da tela, por paridade. */
export const SEQ_PILL_HEIGHT = 15;

/** O respiro de cada lado do número dentro do pill. */
export const SEQ_PILL_PADDING = 4;

/** A largura de um dígito do número, na fonte mono do chip. */
export const SEQ_DIGIT_WIDTH = 6;

/** O vão entre o pill do número e o texto do chip. */
export const SEQ_LEAD_GAP = 5;

/**
 * A largura do pill do número. Com um dígito ele é um círculo; daí em diante
 * cresce com a contagem de dígitos, para o número não sair cortado (UI-01).
 */
export function seqPillWidth(order: number): number {
    return Math.max(
        SEQ_PILL_HEIGHT,
        String(order).length * SEQ_DIGIT_WIDTH + SEQ_PILL_PADDING * 2,
    );
}

/**
 * O quanto o retângulo do chip alarga para abrir espaço ao pill do número.
 * Não é mais um valor fixo: acompanha a largura do pill, que depende dos
 * dígitos do número.
 */
export function seqLead(order: number): number {
    return seqPillWidth(order) + SEQ_LEAD_GAP;
}

/** A largura mínima do bloco da legenda, antes de qualquer linha esticá-lo. */
export const LEGEND_MIN_WIDTH = 90;

export const MONO_FONT = 'IBM Plex Mono, monospace';

export const UI_FONT = 'Archivo, sans-serif';

/** O que separa selo e rótulo dentro do chip da seta. */
export const CHIP_SEPARATOR = '  ·  ';

/**
 * A cor de quando o tema não entregou o token. `currentColor` mantém o arquivo
 * válido e legível em vez de deixar um atributo vazio no lugar de uma cor.
 */
export const FALLBACK_COLOR = 'currentColor';

const XML_ESCAPES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
};

const VAR_REFERENCE = /^var\((--[a-z0-9-]+)\)$/;

export function escapeXml(value: string): string {
    return value.replace(/[&<>"]/g, (char) => XML_ESCAPES[char]);
}

/**
 * A cor pronta para o arquivo. Aceita tanto o token cru quanto a referência
 * `var(--x)` que o motor usa na tela — é o ponto onde o SVG deixa de depender
 * das variáveis CSS do documento.
 */
export function svgColor(palette: SvgPalette, reference: string): string {
    const token = VAR_REFERENCE.exec(reference)?.[1] ?? reference;

    return palette[token] || FALLBACK_COLOR;
}

/**
 * O rótulo do bloco quebrado em linhas de até `max` caracteres, cortado em três
 * — mais do que isso o bloco não comporta, e o desenho exportado tem a mesma
 * altura do da tela.
 */
export function wrapLabel(
    text: string,
    max: number = LABEL_WRAP_CHARS,
): string[] {
    const lines: string[] = [];
    let line = '';

    for (const word of String(text).split(/\s+/)) {
        if (`${line} ${word}`.trim().length > max) {
            if (line !== '') {
                lines.push(line);
            }

            line = word;

            continue;
        }

        line = `${line} ${word}`.trim();
    }

    if (line !== '') {
        lines.push(line);
    }

    return lines.slice(0, MAX_LABEL_LINES);
}

/** O bloco da legenda: o desenho dela e o espaço que ela pede no enquadramento. */
export type SvgLegend = {
    width: number;
    height: number;
    markup: string;
};

/** Uma linha da seção *Ligações* do arquivo, já achatada a partir da legenda. */
type LegendRow = {
    badge: string;
    name: string;
    gloss: string;
    dash: string | null;
    bidir: boolean;
    seq: boolean;
};

function heading(
    x: number,
    y: number,
    text: string,
    size: number,
    spacing: number,
    color: string,
): string {
    return `<text x="${x}" y="${y}" font-family="${MONO_FONT}" font-size="${size}" letter-spacing="${spacing}" fill="${color}">${text}</text>`;
}

/**
 * A amostra do traço, sempre neutra: o padrão vem do tipo, a cor não — só os
 * quadradinhos de categoria têm cor na legenda (US-5.1).
 */
function sampleMarkup(
    x: number,
    cy: number,
    dash: string | null,
    bidir: boolean,
    color: string,
): string {
    const x1 = x + (bidir ? 7 : 2);
    const x2 = x + 31;

    let markup = `<path d="M${x1} ${cy}H${x2}" fill="none" stroke="${color}" stroke-width="1.7" stroke-linecap="round"${dash ? ` stroke-dasharray="${dash}"` : ''}/>`;

    markup += `<path d="M${x2 - 2} ${cy - 3.4}L${x + 36} ${cy}L${x2 - 2} ${cy + 3.4}Z" fill="${color}"/>`;

    if (bidir) {
        markup += `<path d="M${x + 5} ${cy - 3.4}L${x} ${cy}L${x + 5} ${cy + 3.4}Z" fill="${color}"/>`;
    }

    return markup;
}

/** A amostra da linha de sequência: o mesmo pill do chip, em cor neutra. */
function seqSampleMarkup(
    x: number,
    cy: number,
    color: string,
    paper: string,
): string {
    return seqPillMarkup(x + 19, cy - 3.5, 1, color, paper);
}

/**
 * As linhas da seção *Ligações*: os tipos usados, a linha das setas sem tipo e,
 * por último, a linha da ordem das conexões — a mesma ordem da tela.
 */
function legendRows(data: LegendData): LegendRow[] {
    const rows: LegendRow[] = data.links.map((link) => ({
        badge: link.badge,
        name: link.name,
        gloss: link.gloss,
        dash: link.dash,
        bidir: link.bidir,
        seq: false,
    }));

    if (data.untyped) {
        rows.push({
            badge: '',
            name: UNTYPED_NAME,
            gloss: UNTYPED_GLOSS,
            dash: null,
            bidir: false,
            seq: false,
        });
    }

    if (data.sequence) {
        rows.push({
            badge: '',
            name: ORDER_NAME,
            gloss: ORDER_GLOSS,
            dash: null,
            bidir: false,
            seq: true,
        });
    }

    return rows;
}

/**
 * A legenda do arquivo, desenhada num bloco abaixo do diagrama a partir da
 * mesma `legendData` da tela. Devolve também a caixa que ela ocupa, porque é o
 * enquadramento do SVG que precisa reservá-la.
 */
export function svgLegend(
    data: LegendData,
    x: number,
    yTop: number,
    palette: SvgPalette,
): SvgLegend {
    if (data.empty) {
        return { width: 0, height: 0, markup: '' };
    }

    const muted = svgColor(palette, '--ink-3');
    const ink = svgColor(palette, '--ink');
    const ink2 = svgColor(palette, '--ink-2');
    const paper = svgColor(palette, '--paper');

    let markup = '';
    let cy = yTop + 9;
    let width = LEGEND_MIN_WIDTH;

    const grow = (candidate: number): void => {
        width = Math.max(width, candidate);
    };

    markup += heading(x, cy, 'LEGENDA', 9, 1.3, muted);

    if (data.categories.length > 0) {
        cy += 20;
        markup += heading(x, cy, 'BLOCOS', 8.5, 1, muted);

        for (const category of data.categories) {
            cy += 15;

            markup += `<rect x="${x}" y="${(cy - 7.5).toFixed(1)}" width="8" height="8" rx="2" fill="${svgColor(palette, category.color)}"/>`;
            markup += `<text x="${x + 14}" y="${cy}" font-family="${UI_FONT}" font-size="11.5" fill="${ink}">${escapeXml(category.name)}<tspan dx="7" font-family="${MONO_FONT}" font-size="10" fill="${muted}">${category.count}</tspan></text>`;

            grow(14 + category.name.length * 6.4 + 18);
        }
    }

    const rows = legendRows(data);

    if (rows.length > 0) {
        cy += 20;
        markup += heading(x, cy, 'LIGAÇÕES', 8.5, 1, muted);

        for (const row of rows) {
            cy += 16;

            markup += row.seq
                ? seqSampleMarkup(x, cy, muted, paper)
                : sampleMarkup(x, cy - 3.5, row.dash, row.bidir, muted);

            let textX = x + 45;

            if (row.badge !== '') {
                markup += `<text x="${textX}" y="${cy}" font-family="${MONO_FONT}" font-size="10" font-weight="600" fill="${ink2}">${escapeXml(row.badge)}</text>`;
                textX += row.badge.length * 6.1 + 7;
            }

            markup += `<text x="${textX}" y="${cy}" font-family="${UI_FONT}" font-size="11" fill="${muted}">${escapeXml(row.name)}</text>`;

            cy += 13;
            markup += `<text x="${x + 45}" y="${cy}" font-family="${UI_FONT}" font-size="10" fill="${muted}">${escapeXml(row.gloss)}</text>`;

            grow(
                45 +
                    Math.max(
                        row.gloss.length * 4.9,
                        row.badge.length * 6.1 + 7 + row.name.length * 5.6,
                    ),
            );

            cy += 4;
        }
    }

    return {
        width: Math.ceil(width),
        height: Math.ceil(cy - yTop + 4),
        markup,
    };
}

/**
 * O pill do número da conexão: fundo cheio na cor da própria seta e o número
 * na cor do papel. É sólido de propósito — é o que o distingue da caixa de
 * texto do rótulo por forma e preenchimento, não só por cor (UI-01).
 */
function seqPillMarkup(
    cx: number,
    cy: number,
    value: number,
    color: string,
    paper: string,
): string {
    const width = seqPillWidth(value);

    return (
        `<rect x="${(cx - width / 2).toFixed(1)}" y="${(cy - SEQ_PILL_HEIGHT / 2).toFixed(1)}" width="${width.toFixed(1)}" height="${SEQ_PILL_HEIGHT}" rx="${SEQ_PILL_HEIGHT / 2}" fill="${color}"/>` +
        `<text x="${cx.toFixed(1)}" y="${(cy + 3.2).toFixed(1)}" text-anchor="middle" font-family="${MONO_FONT}" font-size="9" font-weight="600" fill="${paper}">${value}</text>`
    );
}

/**
 * O chip da seta: selo e rótulo separados por ponto médio, dentro de um
 * retângulo que alarga o bastante para o pill do número — e que some por
 * completo quando só o número resta, sobrando o pill sozinho (US-4.2).
 */
function chipMarkup(
    geometry: EdgeGeometry,
    chip: EdgeChip,
    order: number | null,
    color: string,
    palette: SvgPalette,
): string {
    const [mx, my] = ptAt(geometry, 0.5);
    const paper = svgColor(palette, '--paper');
    const text = [chip.badge, chip.label].filter(Boolean).join(CHIP_SEPARATOR);

    if (text === '') {
        return order === null ? '' : seqPillMarkup(mx, my, order, color, paper);
    }

    const lead = order === null ? 0 : seqLead(order);
    const width = text.length * 5.9 + 14 + lead;
    const left = mx - width / 2;

    let markup = `<rect x="${left.toFixed(1)}" y="${(my - 9).toFixed(1)}" width="${width.toFixed(1)}" height="18" rx="4" fill="${paper}" stroke="${svgColor(palette, '--line')}"/>`;

    if (order !== null) {
        markup += seqPillMarkup(
            left + SEQ_LEAD_GAP / 2 + seqPillWidth(order) / 2,
            my,
            order,
            color,
            paper,
        );
    }

    markup += `<text x="${(mx + lead / 2).toFixed(1)}" y="${(my + 3.5).toFixed(1)}" text-anchor="middle" font-family="${MONO_FONT}" font-size="10" fill="${svgColor(palette, '--ink-2')}">`;

    if (chip.badge !== '') {
        markup += `<tspan fill="${color}" font-weight="600">${escapeXml(chip.badge)}</tspan>`;
    }

    if (chip.badge !== '' && chip.label !== '') {
        markup += `<tspan fill="${svgColor(palette, '--ink-3')}">${CHIP_SEPARATOR}</tspan>`;
    }

    if (chip.label !== '') {
        markup += `<tspan>${escapeXml(chip.label)}</tspan>`;
    }

    return `${markup}</text>`;
}

function arrowMarkup(
    x: number,
    y: number,
    fromX: number,
    fromY: number,
    color: string,
): string {
    return `<path d="${head(x, y, fromX, fromY).d}" fill="${color}" stroke="none"/>`;
}

/**
 * O bloco: moldura, ícone na cor da categoria, o rótulo quebrado em até três
 * linhas e o nome curto do tipo em maiúsculas — o mesmo que o `CanvasNode`
 * mostra, com a altura que a tela mediu.
 */
function nodeMarkup(
    node: Node,
    index: ComponentIndex,
    heights: NodeHeights,
    palette: SvgPalette,
): string {
    const color = svgColor(palette, colorOf(index, node.type));
    const height = nodeHeight(node, heights);
    const iconKey = index.get(node.type)?.component.icon_key ?? '';
    const icon = (
        hasCanvasIcon(iconKey) ? CANVAS_ICONS[iconKey] : ''
    ).replaceAll('fill="currentColor"', `fill="${color}"`);

    let markup = `<g transform="translate(${node.x} ${node.y})">`;

    markup += `<rect width="${NODE_WIDTH}" height="${height}" rx="10" fill="${svgColor(palette, '--panel')}" stroke="${svgColor(palette, '--line-2')}"/>`;
    markup += `<rect x="${NODE_WIDTH / 2 - 17}" y="11" width="34" height="34" rx="8" fill="${color}" fill-opacity="0.11" stroke="${color}" stroke-opacity="0.28"/>`;
    markup += `<g transform="translate(${NODE_WIDTH / 2 - 10} 21) scale(0.833)" fill="none" stroke="${color}" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${icon}</g>`;

    wrapLabel(node.label).forEach((line, position) => {
        markup += `<text x="${NODE_WIDTH / 2}" y="${58 + position * 14}" text-anchor="middle" font-family="${UI_FONT}" font-weight="600" font-size="12.5" fill="${svgColor(palette, '--ink')}">${escapeXml(line)}</text>`;
    });

    markup += `<text x="${NODE_WIDTH / 2}" y="${height - 9}" text-anchor="middle" font-family="${MONO_FONT}" font-size="8.5" letter-spacing="0.6" fill="${color}">${escapeXml(shortNameOf(index, node.type).toUpperCase())}</text>`;

    return `${markup}</g>`;
}

/** Tudo o que o arquivo precisa saber, que é o mesmo que a tela já tem em mãos. */
export type SvgContext = {
    state: SessionState;
    index: ComponentIndex;
    linkIndex: LinkTypeIndex;
    palette: SvgPalette;
    heights?: NodeHeights;
};

/**
 * O diagrama inteiro em SVG. A ordem de emissão é arestas, blocos e só então os
 * chips: bloco vizinho nunca cobre rótulo no arquivo exportado. Diagrama sem
 * bloco nenhum não tem enquadramento e devolve string vazia — não há o que
 * exportar (US-9.1).
 */
export function buildSVG(context: SvgContext): string {
    const { state, index, linkIndex, palette } = context;
    const heights = context.heights ?? {};
    const bounds = diagramBounds(state.nodes, heights);

    if (!bounds) {
        return '';
    }

    const legend = svgLegend(
        legendData(state, index, linkIndex),
        bounds.x0,
        bounds.y1 + LEGEND_GAP,
        palette,
    );

    const width =
        Math.max(bounds.x1 - bounds.x0, legend.width) + SVG_PADDING * 2;
    const height =
        bounds.y1 -
        bounds.y0 +
        (legend.height > 0 ? legend.height + LEGEND_GAP : 0) +
        SVG_PADDING * 2;

    let body = '';
    let chips = '';

    for (const edge of state.edges) {
        const geometry = bez(edge, state.nodes, heights);

        if (!geometry) {
            continue;
        }

        const color = svgColor(palette, edgeColor(index, edge, state.nodes));
        const dash = dashOf(linkIndex, edge);

        body += `<path d="${geometry.d}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linecap="round"${dash ? ` stroke-dasharray="${dash}"` : ''}/>`;
        body += arrowMarkup(
            geometry.x2,
            geometry.y2,
            geometry.c2[0],
            geometry.c2[1],
            color,
        );

        if (edge.bidir) {
            body += arrowMarkup(
                geometry.x1,
                geometry.y1,
                geometry.c1[0],
                geometry.c1[1],
                color,
            );
        }

        chips += chipMarkup(
            geometry,
            edgeChip(linkIndex, edge),
            state.showConnectionOrder ? edge.order : null,
            color,
            palette,
        );
    }

    for (const node of state.nodes) {
        body += nodeMarkup(node, index, heights, palette);
    }

    const x = bounds.x0 - SVG_PADDING;
    const y = bounds.y0 - SVG_PADDING;

    return (
        `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="${x} ${y} ${width} ${height}">` +
        `<rect x="${x}" y="${y}" width="${width}" height="${height}" fill="${svgColor(palette, '--paper')}"/>` +
        `${body}${chips}${legend.markup}</svg>`
    );
}
