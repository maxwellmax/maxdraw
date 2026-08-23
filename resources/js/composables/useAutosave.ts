import { onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { sendSessionState } from '@/lib/sessionTransport';
import type { SessionTransport } from '@/prancheta/autosave';
import { Autosave } from '@/prancheta/autosave';
import type { SessionStorage } from '@/prancheta/resume';
import { readCache, resolveBoot } from '@/prancheta/resume';
import { bodyFromPayload, recordFrom, SessionStore } from '@/prancheta/session';
import type { SessionPayload } from '@/types';

export type UseAutosaveReturn = {
    autosave: Autosave;
    saveNow: () => Promise<void>;
};

/**
 * O `localStorage`, quando o navegador deixa: em aba anônima do Safari o
 * acesso lança, e a prancheta precisa abrir mesmo assim.
 */
export function browserStorage(): SessionStorage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}

/**
 * Monta o store com o estado que prevalece ao carregar: o rascunho local
 * quando ele é o mais novo, o do servidor quando foi ele que andou — e nesse
 * caso o usuário é avisado, em vez de o servidor sobrescrever em silêncio
 * (US-8.3).
 */
export function createSessionStore(payload: SessionPayload): SessionStore {
    const server = {
        id: payload.id,
        body: bodyFromPayload(payload),
        updatedAt: payload.updated_at,
    };

    const boot = resolveBoot(server, readCache(browserStorage(), server.id));

    if (boot.serverIsNewer) {
        useToast().warn('serverVersionIsNewer');
    }

    return new SessionStore(
        server.id,
        recordFrom(boot.body),
        server.updatedAt,
        server.body,
    );
}

/**
 * Liga o store ao autosave: toda alteração do payload reinicia os dois
 * temporizadores, e fechar a aba grava uma última vez no navegador (US-8.1).
 */
export function useAutosave(
    store: SessionStore,
    send: SessionTransport = sendSessionState,
): UseAutosaveReturn {
    const { warn } = useToast();

    const autosave = reactive(
        new Autosave({
            store,
            storage: browserStorage(),
            send,
            onServerIsNewer: () => warn('serverVersionIsNewer'),
        }),
    ) as Autosave;

    const stopWatching = watch(
        () => store.signature,
        () => autosave.touch(),
    );

    let unbindUnload = (): void => {};

    onMounted(() => {
        unbindUnload = autosave.bindUnload(window);

        // O rascunho local que venceu o boot ainda não subiu.
        if (store.isDirty) {
            autosave.touch();
        }
    });

    onBeforeUnmount(() => {
        unbindUnload();
        stopWatching();
        autosave.stop();
    });

    return { autosave, saveNow: () => autosave.save() };
}
