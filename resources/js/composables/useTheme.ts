import type { ComputedRef, Ref } from 'vue';
import { computed, readonly, ref } from 'vue';
import type { Theme, ThemeVars } from '@/prancheta/theme';
import {
    isTheme,
    nextTheme,
    resolveTheme,
    THEME_STORAGE_KEY,
    THEME_VARS,
} from '@/prancheta/theme';

export type UseThemeReturn = {
    preference: Readonly<Ref<Theme | null>>;
    resolved: ComputedRef<Theme>;
    vars: Readonly<Ref<ThemeVars>>;
    toggle: () => void;
    setTheme: (theme: Theme | null) => void;
};

const EMPTY_VARS = Object.fromEntries(
    THEME_VARS.map((name) => [name, '']),
) as ThemeVars;

const preference = ref<Theme | null>(null);
const systemPrefersDark = ref(false);
const vars = ref<ThemeVars>({ ...EMPTY_VARS });

let initialized = false;

function darkMediaQuery(): MediaQueryList | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
}

function readStoredTheme(): Theme | null {
    try {
        const stored = localStorage.getItem(THEME_STORAGE_KEY);

        return isTheme(stored) ? stored : null;
    } catch {
        return null;
    }
}

function storeTheme(theme: Theme | null): void {
    try {
        if (theme) {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        } else {
            localStorage.removeItem(THEME_STORAGE_KEY);
        }
    } catch {
        // Navegador sem storage disponível: o tema vale só para esta aba.
    }
}

/**
 * Relê os tokens do tema corrente. O canvas desenha as arestas com cores vindas
 * daqui, então cada troca de tema precisa passar por esta função antes do
 * próximo quadro — senão as setas ficam com a cor do tema anterior.
 */
function readThemeVars(): ThemeVars {
    if (typeof window === 'undefined') {
        return { ...EMPTY_VARS };
    }

    const styles = getComputedStyle(document.documentElement);

    return Object.fromEntries(
        THEME_VARS.map((name) => [name, styles.getPropertyValue(name).trim()]),
    ) as ThemeVars;
}

function refreshVars(): void {
    if (typeof window === 'undefined') {
        return;
    }

    requestAnimationFrame(() => {
        vars.value = readThemeVars();
    });
}

function setTheme(theme: Theme | null): void {
    preference.value = theme;

    if (typeof document !== 'undefined') {
        if (theme) {
            document.documentElement.setAttribute('data-theme', theme);
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }

    storeTheme(theme);
    refreshVars();
}

/**
 * A preferência de tema é do navegador, não da sessão de treino: ela nasce do
 * `localStorage` e nunca é enviada ao servidor.
 */
export function initializeTheme(): void {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;

    const media = darkMediaQuery();

    systemPrefersDark.value = media?.matches ?? false;
    preference.value = readStoredTheme();

    if (preference.value) {
        document.documentElement.setAttribute('data-theme', preference.value);
    }

    vars.value = readThemeVars();

    media?.addEventListener('change', (event) => {
        systemPrefersDark.value = event.matches;
        refreshVars();
    });
}

export function useTheme(): UseThemeReturn {
    initializeTheme();

    return {
        preference: readonly(preference),
        resolved: computed(() =>
            resolveTheme(preference.value, systemPrefersDark.value),
        ),
        vars: readonly(vars) as Readonly<Ref<ThemeVars>>,
        toggle: () =>
            setTheme(nextTheme(preference.value, systemPrefersDark.value)),
        setTheme,
    };
}
