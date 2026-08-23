/**
 * O arquivo que o botão SVG baixa. O conteúdo é montado pelo motor do canvas; o
 * que mora aqui é o nome dele — o slug do problema escolhido, ou `prancheta`
 * quando o treino é livre (US-9.1).
 */

export const SVG_MIME_TYPE = 'image/svg+xml;charset=utf-8';

export const SVG_EXTENSION = '.svg';

/** O nome do arquivo de quem desenhou sem escolher problema. */
export const FREE_BOARD_NAME = 'prancheta';

export function exportFileName(problem: { slug: string } | null): string {
    return (problem?.slug ?? FREE_BOARD_NAME) + SVG_EXTENSION;
}
