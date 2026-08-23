import { describe, expect, it } from 'vitest';
import { CanvasEngine } from '@/canvas/engine';
import { catalogFixture, edgeFixture, nodeFixture } from '@/canvas/fixtures';
import { sessionRecordFixture } from './fixtures';
import type { SessionBody } from './session';
import { bodyFrom, recordFrom, SessionStore } from './session';

const SERVER_UPDATED_AT = '2026-08-22T12:00:00.000000Z';

/**
 * A composição de produção: o motor governa o diagrama, e o diagrama é o mesmo
 * objeto que o store persiste.
 */
function boot(): { store: SessionStore; engine: CanvasEngine } {
    const store = new SessionStore(
        12,
        sessionRecordFixture({
            nodes: [nodeFixture('n1'), nodeFixture('n2', 400)],
        }),
        SERVER_UPDATED_AT,
    );

    return { store, engine: new CanvasEngine(store.state, catalogFixture()) };
}

/** Um caso por campo persistido da sessão (US-8.1). */
const PERSISTED_CHANGES: Array<
    [string, (store: SessionStore, engine: CanvasEngine) => void]
> = [
    ['problema escolhido', (store) => store.setProblemId(7)],
    ['nós', (_store, engine) => void engine.addNode('api')],
    [
        'rótulo do bloco',
        (_store, engine) => void engine.renameNode('n1', 'Feed'),
    ],
    ['arestas', (_store, engine) => void engine.addEdge('n1', 'n2')],
    ['ordem exibida', (store) => store.setShowConnectionOrder(false)],
    ['checklist', (store) => store.setCheck(7, true)],
    ['notas', (store) => store.setNotes('particionar por user_id')],
    ['estimativas', (store) => store.setEstimate({ ratio: 200 })],
    ['tempo decorrido', (store) => store.setElapsedSeconds(120)],
    ['duração', (store) => store.setDurationMinutes(60)],
];

describe('SessionStore', () => {
    it('any_state_change_marks_session_dirty', () => {
        for (const [field, change] of PERSISTED_CHANGES) {
            const { store, engine } = boot();

            expect(store.isDirty, field).toBe(false);

            change(store, engine);

            expect(store.isDirty, field).toBe(true);
        }
    });

    it('view_and_selection_changes_do_not_mark_dirty', () => {
        const { store, engine } = boot();

        engine.setSize({ width: 1200, height: 800 });
        engine.panBy(40, 20);
        engine.zoomBy(1.2);
        engine.wheel(-120, 300, 300);
        engine.selectNode('n1');
        engine.select({ kind: 'edge', id: 'e1' });
        engine.measure('n1', 96);
        engine.setLegendWidth(280);
        engine.fit();

        expect(store.isDirty).toBe(false);
    });

    it('guarda o estado inteiro da sessão num payload só', () => {
        const { store } = boot();

        expect(Object.keys(store.body()).sort()).toEqual([
            'checks',
            'duration_minutes',
            'edges',
            'elapsed_seconds',
            'estimate',
            'nodes',
            'notes',
            'problem_id',
            'show_connection_order',
        ]);
    });

    it('nasce salva e volta a ficar salva quando o payload é confirmado', () => {
        const { store } = boot();

        expect(store.isDirty).toBe(false);

        store.setNotes('rascunho');

        expect(store.isDirty).toBe(true);

        store.markSaved(store.signature, '2026-08-22T12:30:00.000000Z');

        expect(store.isDirty).toBe(false);
        expect(store.serverUpdatedAt).toBe('2026-08-22T12:30:00.000000Z');
    });

    it('marca salvo o payload enviado, não o que chegou durante o envio', () => {
        const { store } = boot();

        store.setNotes('primeira frase');

        const inFlight = store.signature;

        store.setNotes('primeira frase e a segunda');
        store.markSaved(inFlight, SERVER_UPDATED_AT);

        expect(store.isDirty).toBe(true);
    });

    it('nasce suja quando o rascunho local é mais novo que o do servidor', () => {
        const state = sessionRecordFixture({ notes: 'rascunho local' });
        const server: SessionBody = bodyFrom(sessionRecordFixture());
        const store = new SessionStore(12, state, SERVER_UPDATED_AT, server);

        expect(store.isDirty).toBe(true);
    });

    it('reaplica um payload inteiro sem trocar o objeto de estado', () => {
        const { store, engine } = boot();
        const body = bodyFrom(
            sessionRecordFixture({
                nodes: [nodeFixture('n9')],
                notes: 'do navegador',
                elapsedSeconds: 742,
                durationMinutes: 60,
                showConnectionOrder: false,
            }),
        );

        store.restore(body);

        expect(engine.nodes).toHaveLength(1);
        expect(engine.nodes[0].id).toBe('n9');
        expect(engine.showConnectionOrder).toBe(false);
        expect(store.notes).toBe('do navegador');
        expect(store.elapsedSeconds).toBe(742);
        expect(store.durationMinutes).toBe(60);
    });

    it('grava o problema escolhido na sessão corrente', () => {
        const { store } = boot();

        expect(store.problemId).toBeNull();

        store.setProblemId(7);

        expect(store.problemId).toBe(7);
        expect(store.body().problem_id).toBe(7);

        store.setProblemId(null);

        expect(store.body().problem_id).toBeNull();
    });
});

describe('bodyFrom / recordFrom', () => {
    it('traduz os nomes do servidor nos dois sentidos', () => {
        const record = sessionRecordFixture({
            problemId: 4,
            nodes: [nodeFixture('n1')],
            notes: 'notas',
            elapsedSeconds: 90,
            durationMinutes: 30,
            showConnectionOrder: false,
        });

        expect(bodyFrom(record)).toMatchObject({
            problem_id: 4,
            elapsed_seconds: 90,
            duration_minutes: 30,
            show_connection_order: false,
        });

        expect(recordFrom(bodyFrom(record))).toEqual(record);
    });

    it('session_body_carries_the_order_of_every_edge', () => {
        const body = bodyFrom(
            sessionRecordFixture({
                nodes: [nodeFixture('n1'), nodeFixture('n2', 400)],
                edges: [
                    edgeFixture('e1', 'n1', 'n2', 1),
                    edgeFixture('e2', 'n2', 'n1'),
                ],
            }),
        );

        expect(body.show_connection_order).toBe(true);
        expect(body.edges.map((edge) => edge.order)).toEqual([1, null]);
    });

    it('copia nós e arestas para o payload do servidor não virar o do motor', () => {
        const record = sessionRecordFixture({ nodes: [nodeFixture('n1')] });
        const restored = recordFrom(bodyFrom(record));

        expect(restored.nodes[0]).not.toBe(record.nodes[0]);
    });
});
