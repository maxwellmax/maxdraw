export type Theme = 'light' | 'dark';

export const THEME_STORAGE_KEY = 'sd-theme';

/**
 * Os tokens que o motor do canvas precisa reler a cada troca de tema — a mesma
 * lista que o `readVars` do protótipo mantém.
 */
export const THEME_VARS = [
    '--c-client',
    '--c-edge',
    '--c-compute',
    '--c-data',
    '--c-async',
    '--c-ops',
    '--ink-3',
    '--accent',
    '--panel',
    '--line',
    '--ink',
    '--ink-2',
    '--paper',
    '--line-2',
] as const;

export type ThemeVar = (typeof THEME_VARS)[number];

export type ThemeVars = Record<ThemeVar, string>;

/**
 * O ciclo do botão de tema: sem preferência salva vai para o oposto do sistema,
 * e daí em diante alterna entre claro e escuro. Nunca volta para "sem
 * preferência" — trocar o tema é sempre uma escolha explícita.
 */
export function nextTheme(
    current: Theme | null,
    systemPrefersDark: boolean,
): Theme {
    if (current) {
        return current === 'dark' ? 'light' : 'dark';
    }

    return systemPrefersDark ? 'light' : 'dark';
}

/**
 * O tema que a tela está mostrando: a preferência salva quando existe, senão o
 * que o sistema operacional pede.
 */
export function resolveTheme(
    stored: Theme | null,
    systemPrefersDark: boolean,
): Theme {
    return stored ?? (systemPrefersDark ? 'dark' : 'light');
}

export function isTheme(value: unknown): value is Theme {
    return value === 'light' || value === 'dark';
}
