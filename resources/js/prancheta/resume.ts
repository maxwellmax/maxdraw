import type { SessionBody } from './session';

export const SESSION_CACHE_PREFIX = 'sd-session-';

/**
 * A versão do rascunho local. Foi a `2` quando a ordem das conexões virou campo
 * da aresta: o formato v1 guardava o modo de numeração e nenhuma ordem, então
 * `isEntry()` o descarta no boot e o estado do servidor vence — trabalho não
 * sincronizado que só exista em v1 se perde, o que é preferível a reidratar um
 * rascunho com o contrato anterior (RF-18).
 */
export const SESSION_CACHE_VERSION = 2;

/**
 * O que o navegador guarda de uma sessão entre uma gravação e a próxima. Além
 * do payload, guarda de qual versão do servidor essas edições partiram
 * (`serverUpdatedAt`) e quando foram gravadas (`savedAt`) — é com esses dois
 * que o boot decide quem é mais novo (US-8.3).
 */
export type SessionCacheEntry = {
    version: number;
    id: number;
    savedAt: number;
    serverUpdatedAt: string | null;
    body: SessionBody;
};

/** O mínimo do `localStorage` que o autosave usa. */
export type SessionStorage = {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
};

export type BootSource = 'local' | 'server';

export type BootResolution = {
    body: SessionBody;
    source: BootSource;
    serverIsNewer: boolean;
};

export type ServerSession = {
    id: number;
    body: SessionBody;
    updatedAt: string | null;
};

export function sessionCacheKey(id: number): string {
    return SESSION_CACHE_PREFIX + id;
}

/**
 * Lê o rascunho local. Qualquer coisa ilegível é tratada como ausência: um
 * item corrompido não pode impedir a prancheta de abrir.
 */
export function readCache(
    storage: SessionStorage | null,
    id: number,
): SessionCacheEntry | null {
    if (!storage) {
        return null;
    }

    try {
        const raw = storage.getItem(sessionCacheKey(id));
        const entry = raw ? (JSON.parse(raw) as SessionCacheEntry) : null;

        return isEntry(entry) && entry.id === id ? entry : null;
    } catch {
        return null;
    }
}

/**
 * Grava o rascunho local. Cota estourada ou armazenamento bloqueado não
 * derrubam o treino — o trabalho segue na memória e no servidor.
 */
export function writeCache(
    storage: SessionStorage | null,
    entry: SessionCacheEntry,
): void {
    if (!storage) {
        return;
    }

    try {
        storage.setItem(sessionCacheKey(entry.id), JSON.stringify(entry));
    } catch {
        return;
    }
}

export function clearCache(storage: SessionStorage | null, id: number): void {
    if (!storage) {
        return;
    }

    try {
        storage.removeItem(sessionCacheKey(id));
    } catch {
        return;
    }
}

/**
 * Quem prevalece ao carregar a prancheta. O rascunho local vence quando partiu
 * da mesma versão que o servidor está entregando (edição que ainda não subiu)
 * ou quando é mais novo que ela. Quando o servidor andou por fora desta aba, é
 * ele que abre — e o usuário fica sabendo, em vez de perder o desenho de lá em
 * silêncio (US-8.3).
 */
export function resolveBoot(
    server: ServerSession,
    cached: SessionCacheEntry | null,
): BootResolution {
    if (!cached || cached.id !== server.id) {
        return { body: server.body, source: 'server', serverIsNewer: false };
    }

    const sameStartingPoint = cached.serverUpdatedAt === server.updatedAt;

    if (sameStartingPoint || cached.savedAt > timeOf(server.updatedAt)) {
        return { body: cached.body, source: 'local', serverIsNewer: false };
    }

    return { body: server.body, source: 'server', serverIsNewer: true };
}

/**
 * A mais nova de duas versões do servidor. A gravação local desta aba não pode
 * apagar a marca de que outra aba já subiu algo mais novo.
 */
export function newestVersion(
    left: string | null,
    right: string | null,
): string | null {
    return timeOf(right) > timeOf(left) ? right : left;
}

/**
 * Outra aba salvou depois desta: o rascunho gravado no navegador partiu de uma
 * versão do servidor mais nova que a desta aba.
 */
export function serverMovedOn(
    cached: SessionCacheEntry | null,
    baseline: string | null,
): boolean {
    if (!cached) {
        return false;
    }

    return timeOf(cached.serverUpdatedAt) > timeOf(baseline);
}

function timeOf(updatedAt: string | null): number {
    const parsed = updatedAt ? Date.parse(updatedAt) : Number.NaN;

    return Number.isNaN(parsed) ? 0 : parsed;
}

function isEntry(entry: unknown): entry is SessionCacheEntry {
    if (!entry || typeof entry !== 'object') {
        return false;
    }

    const candidate = entry as Partial<SessionCacheEntry>;

    return (
        candidate.version === SESSION_CACHE_VERSION &&
        typeof candidate.id === 'number' &&
        typeof candidate.savedAt === 'number' &&
        !!candidate.body
    );
}
