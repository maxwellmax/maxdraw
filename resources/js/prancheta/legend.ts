/**
 * O recolhimento da legenda é preferência do **navegador**, não da sessão de
 * treino: mora no `localStorage`, sob a mesma chave do protótipo, e nunca entra
 * no payload do autosave (US-5.2).
 */

export const LEGEND_STORAGE_KEY = 'sd-legend';

/** O protótipo grava `'0'` para recolhida e `'1'` para expandida. */
export const LEGEND_CLOSED = '0';

export const LEGEND_OPEN = '1';

/** Sem preferência gravada a legenda nasce aberta: ela é o que ensina. */
export function isLegendOpen(stored: string | null): boolean {
    return stored !== LEGEND_CLOSED;
}

export function legendStorageValue(open: boolean): string {
    return open ? LEGEND_OPEN : LEGEND_CLOSED;
}
