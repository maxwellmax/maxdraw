import type { Ref } from 'vue';
import { readonly, ref } from 'vue';
import {
    isLegendOpen,
    LEGEND_STORAGE_KEY,
    legendStorageValue,
} from '@/prancheta/legend';

export type UseLegendReturn = {
    open: Readonly<Ref<boolean>>;
    toggle: () => void;
};

const open = ref(true);

let initialized = false;

function readStoredOpen(): boolean {
    try {
        return isLegendOpen(localStorage.getItem(LEGEND_STORAGE_KEY));
    } catch {
        return true;
    }
}

function storeOpen(value: boolean): void {
    try {
        localStorage.setItem(LEGEND_STORAGE_KEY, legendStorageValue(value));
    } catch {
        // Navegador sem storage disponível: a preferência vale só para esta aba.
    }
}

/**
 * O painel da legenda e o enquadramento leem o mesmo estado, então ele é um
 * `ref` de módulo: recolher a legenda tem de devolver ao "enquadrar tudo" a
 * largura que ela deixou de ocupar (US-5.2, US-3.4).
 *
 * A preferência nasce do `localStorage` e nunca sobe para o servidor — ela é do
 * navegador, não da sessão de treino.
 */
export function useLegend(): UseLegendReturn {
    if (!initialized && typeof window !== 'undefined') {
        initialized = true;
        open.value = readStoredOpen();
    }

    return {
        open: readonly(open),
        toggle: () => {
            open.value = !open.value;
            storeOpen(open.value);
        },
    };
}
