/**
 * As quatro abas do painel de treino, na ordem em que aparecem.
 */
export const DRILL_TABS = [
    { id: 'roteiro', label: 'Roteiro' },
    { id: 'enunciado', label: 'Enunciado' },
    { id: 'calc', label: 'Estimativas' },
    { id: 'notas', label: 'Notas' },
] as const;

export type DrillTabId = (typeof DRILL_TABS)[number]['id'];

export const DEFAULT_DRILL_TAB: DrillTabId = 'roteiro';
