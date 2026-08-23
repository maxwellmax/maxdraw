import { describe, expect, it } from 'vitest';
import { nodeFixture } from '@/canvas/fixtures';
import { memoryStorage, sessionRecordFixture } from './fixtures';
import type { SessionCacheEntry, ServerSession } from './resume';
import {
    readCache,
    resolveBoot,
    SESSION_CACHE_VERSION,
    serverMovedOn,
    sessionCacheKey,
    writeCache,
} from './resume';
import type { SessionBody } from './session';
import { bodyFrom, recordFrom, SessionStore } from './session';

const SESSION_ID = 12;

const SERVER_UPDATED_AT = '2026-08-22T12:00:00.000000Z';

const SERVER_TIME = Date.parse(SERVER_UPDATED_AT);

function serverSession(body?: SessionBody): ServerSession {
    return {
        id: SESSION_ID,
        body: body ?? bodyFrom(sessionRecordFixture()),
        updatedAt: SERVER_UPDATED_AT,
    };
}

function cacheEntry(
    overrides: Partial<SessionCacheEntry> = {},
): SessionCacheEntry {
    return {
        version: SESSION_CACHE_VERSION,
        id: SESSION_ID,
        savedAt: SERVER_TIME + 5000,
        serverUpdatedAt: SERVER_UPDATED_AT,
        body: bodyFrom(sessionRecordFixture({ notes: 'rascunho local' })),
        ...overrides,
    };
}

describe('resolveBoot', () => {
    it('local_state_is_reapplied_after_reload', () => {
        const storage = memoryStorage();
        const draft = bodyFrom(
            sessionRecordFixture({
                nodes: [nodeFixture('n1')],
                notes: 'escrito antes de o envio sair',
            }),
        );

        writeCache(storage, cacheEntry({ body: draft }));

        const server = serverSession();
        const boot = resolveBoot(server, readCache(storage, SESSION_ID));

        expect(boot.source).toBe('local');
        expect(boot.serverIsNewer).toBe(false);
        expect(boot.body).toEqual(draft);

        const store = new SessionStore(
            SESSION_ID,
            recordFrom(boot.body),
            server.updatedAt,
            server.body,
        );

        expect(store.notes).toBe('escrito antes de o envio sair');
        expect(store.isDirty).toBe(true);
    });

    it('newer_local_state_wins_on_boot', () => {
        const boot = resolveBoot(
            serverSession(),
            cacheEntry({
                serverUpdatedAt: '2026-08-22T11:00:00.000000Z',
                savedAt: SERVER_TIME + 60000,
            }),
        );

        expect(boot.source).toBe('local');
        expect(boot.serverIsNewer).toBe(false);
        expect(boot.body.notes).toBe('rascunho local');
    });

    it('newer_server_version_warns_instead_of_overwriting', () => {
        const server = serverSession();
        const boot = resolveBoot(
            server,
            cacheEntry({
                serverUpdatedAt: '2026-08-22T11:00:00.000000Z',
                savedAt: SERVER_TIME - 60000,
            }),
        );

        expect(boot.source).toBe('server');
        expect(boot.serverIsNewer).toBe(true);
        expect(boot.body).toEqual(server.body);
    });

    it('abre com o servidor quando não há rascunho local', () => {
        const boot = resolveBoot(serverSession(), null);

        expect(boot.source).toBe('server');
        expect(boot.serverIsNewer).toBe(false);
    });

    it('ignora rascunho de outra sessão', () => {
        const boot = resolveBoot(serverSession(), cacheEntry({ id: 99 }));

        expect(boot.source).toBe('server');
        expect(boot.serverIsNewer).toBe(false);
    });
});

describe('cache do navegador', () => {
    it('guarda uma sessão por chave', () => {
        const storage = memoryStorage();

        writeCache(storage, cacheEntry());

        expect([...storage.items.keys()]).toEqual([
            sessionCacheKey(SESSION_ID),
        ]);
        expect(readCache(storage, SESSION_ID)?.body.notes).toBe(
            'rascunho local',
        );
    });

    it('trata rascunho ilegível como ausência', () => {
        const storage = memoryStorage();

        storage.setItem(sessionCacheKey(SESSION_ID), '{isso não é json');

        expect(readCache(storage, SESSION_ID)).toBeNull();
    });

    it('v1_draft_is_discarded_on_boot', () => {
        const storage = memoryStorage();

        expect(SESSION_CACHE_VERSION).toBe(2);

        writeCache(storage, cacheEntry({ version: 1 }));

        expect(readCache(storage, SESSION_ID)).toBeNull();
        expect(
            resolveBoot(serverSession(), readCache(storage, SESSION_ID)),
        ).toMatchObject({ source: 'server' });
    });

    it('v2_draft_is_still_rehydrated', () => {
        const storage = memoryStorage();

        writeCache(storage, cacheEntry({ version: SESSION_CACHE_VERSION }));

        expect(readCache(storage, SESSION_ID)?.body.notes).toBe(
            'rascunho local',
        );
        expect(
            resolveBoot(serverSession(), readCache(storage, SESSION_ID)),
        ).toMatchObject({ source: 'local' });
    });

    it('não derruba o treino quando o navegador recusa gravar', () => {
        const full = {
            ...memoryStorage(),
            setItem: (): void => {
                throw new Error('QuotaExceededError');
            },
        };

        expect(() => writeCache(full, cacheEntry())).not.toThrow();
    });
});

describe('serverMovedOn', () => {
    it('acusa quando outra aba salvou depois desta', () => {
        expect(
            serverMovedOn(
                cacheEntry({ serverUpdatedAt: '2026-08-22T12:30:00.000000Z' }),
                SERVER_UPDATED_AT,
            ),
        ).toBe(true);
    });

    it('não acusa a própria gravação desta aba', () => {
        expect(serverMovedOn(cacheEntry(), SERVER_UPDATED_AT)).toBe(false);
        expect(serverMovedOn(null, SERVER_UPDATED_AT)).toBe(false);
    });
});
