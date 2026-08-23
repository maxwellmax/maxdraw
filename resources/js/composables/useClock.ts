import { onBeforeUnmount, reactive } from 'vue';
import type { Autosave } from '@/prancheta/autosave';
import { SessionClock } from '@/prancheta/clock';
import type { SessionStore } from '@/prancheta/session';

export type UseClockReturn = {
    clock: SessionClock;
};

/**
 * Liga o cronômetro ao store e ao autosave: contar altera o payload, e o
 * relógio ainda grava o tempo no navegador de tempos em tempos — o envio ao
 * servidor continua sendo do debounce, que não sobe um tique por vez (US-6.1).
 *
 * O relógio abre pausado porque é assim que ele nasce: o servidor guarda o
 * decorrido, nunca o "rodando".
 */
export function useClock(
    store: SessionStore,
    autosave: Autosave,
): UseClockReturn {
    const clock = reactive(
        new SessionClock({ store, persist: () => autosave.saveLocal() }),
    ) as SessionClock;

    onBeforeUnmount(() => clock.stop());

    return { clock };
}
