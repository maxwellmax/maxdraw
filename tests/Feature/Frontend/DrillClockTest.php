<?php

use App\Models\Phase;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * O cronômetro do roteiro (Phase 14): as fatias derivadas dos pesos, o relógio
 * que para sozinho e o seletor de duração. Quem testa o comportamento é o
 * Vitest; o que a suíte PHP guarda aqui é o contrato — os pesos e as durações
 * vindos do catálogo, a ligação com o store e a cobertura da fase.
 */
it('deriva as fatias do peso da fase, sem catálogo paralelo no cliente', function () {
    expect(frontendSource('prancheta/clock.ts'))
        ->toContain('export function bounds(')
        ->toContain('export function curPhase(')
        ->toContain('export function phaseSegments(')
        ->toContain('export const WARN_RATIO = 0.85;');

    seedCatalog();

    $client = frontendSource('prancheta/clock.ts');

    foreach (Phase::query()->active()->get() as $phase) {
        expect($client)->not->toContain($phase->name)
            ->and($client)->not->toContain((string) (float) $phase->weight);
    }
});

it('mantém a fixture do Vitest fiel aos pesos do catálogo', function () {
    seedCatalog();

    preg_match_all(
        "/slug: '([a-z-]+)',\s+name: '([^']+)',\s+weight: ([0-9.]+),\s+position: (\d+),/u",
        frontendSource('prancheta/fixtures.ts'),
        $fixture,
        PREG_SET_ORDER
    );

    $client = array_map(fn (array $match): array => [
        'slug' => $match[1],
        'name' => $match[2],
        'weight' => (float) $match[3],
        'position' => (int) $match[4],
    ], $fixture);

    expect($client)->toBe(Phase::query()->active()->get()
        ->map(fn (Phase $phase): array => [
            'slug' => $phase->slug,
            'name' => $phase->name,
            'weight' => (float) $phase->weight,
            'position' => $phase->position,
        ])
        ->all());
});

it('tira os três botões de duração do catálogo, não de lista fixa', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('durationsFrom(props.catalog.session_durations)')
        ->toContain(':durations="durations"');

    expect(frontendSource('components/prancheta/DrillClock.vue'))
        ->toContain('v-for="minutes in durations"')
        ->not->toContain('[30, 45, 60]');

    expect(frontendSource('prancheta/clock.ts'))->not->toContain('[30, 45, 60]');

    seedCatalog();

    expect(SessionDuration::query()->active()->pluck('minutes')->all())->toBe([30, 45, 60])
        ->and(SessionDuration::query()->where('is_default', true)->value('minutes'))->toBe(45);
});

it('abre uma sessão nova em 45 minutos e no tempo zero', function () {
    seedCatalog();

    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.duration_minutes', 45)
            ->where('session.elapsed_seconds', 0)
            ->count('catalog.session_durations', 3)
            ->count('catalog.phases', 5)
            ->etc());
});

it('mostra o decorrido, o total, a fase corrente e a barra proporcional', function (string $testId) {
    expect(frontendSource('components/prancheta/DrillClock.vue'))
        ->toContain('data-testid="'.$testId.'"');
})->with([
    'clock',
    'clock-elapsed',
    'clock-total',
    'clock-over',
    'clock-toggle',
    'clock-reset',
    'clock-phase-number',
    'clock-phase-name',
    'clock-bar',
    'clock-slice',
    'duration-picker',
]);

it('muda de aparência aos 85% e ao estourar o total', function () {
    expect(frontendSource('components/prancheta/DrillClock.vue'))
        ->toContain("warn: 'text-sd-warn',")
        ->toContain("over: 'text-sd-crit',")
        ->toContain(':disabled="finished"')
        ->toContain('tempo esgotado');

    expect(pranchetaCss())
        ->toContain('--color-sd-warn: var(--warn);')
        ->toContain('--color-sd-crit: var(--crit);');
});

it('dá à fatia a largura do peso e preenche só a corrente', function () {
    expect(frontendSource('components/prancheta/DrillClock.vue'))
        ->toContain(':style="{ flex: segment.weight }"')
        ->toContain('v-if="segment.state === \'current\'"')
        ->toContain('`${segment.progress * 100}%`');
});

it('liga o relógio ao store e ao autosave, e abre pausado', function () {
    expect(frontendSource('composables/useClock.ts'))
        ->toContain('new SessionClock({ store, persist: () => autosave.saveLocal() })')
        ->toContain('onBeforeUnmount(() => clock.stop())');

    expect(frontendSource('prancheta/clock.ts'))
        ->toContain('running = false;')
        ->toContain('export const CLOCK_PERSIST_MS = 20000;');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('useClock(store, autosave)')
        ->toContain('@toggle="clock.toggle()"')
        ->toContain('@reset="clock.reset()"')
        ->toContain('@select-duration="clock.setDurationMinutes($event)"')
        ->not->toContain('setInterval');
});

it('grava o decorrido e a duração na sessão e os devolve ao reabrir', function () {
    seedCatalog();

    $user = User::factory()->create();
    $session = TrainingSession::factory()->withDiagram(3, 2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody([
            'elapsed_seconds' => 1234,
            'duration_minutes' => 30,
        ]))
        ->assertOk();

    expect($session->refresh()->elapsed_seconds)->toBe(1234)
        ->and($session->duration_minutes)->toBe(30);

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.elapsed_seconds', 1234)
            ->where('session.duration_minutes', 30)
            ->etc());
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'bounds_for_45_minutes_matches_5_5_8_12_15',
    'bounds_sum_equals_total_duration',
    'curPhase_advances_at_each_boundary',
    'curPhase_clamps_to_last_phase_after_total',
    'changing_duration_preserves_elapsed',
    'running_clock_persists_elapsed_periodically',
    'clock_resumes_paused_at_persisted_elapsed',
]);
