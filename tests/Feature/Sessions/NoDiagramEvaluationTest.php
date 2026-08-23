<?php

use App\Models\Problem;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

/**
 * A decisão que define o escopo do produto: a ferramenta não avalia o treino
 * (US-10.1). O gabarito é lista estática, e a conferência é do próprio
 * usuário — não existe rota, cálculo ou tela que compare o desenho com os
 * tópicos.
 */
beforeEach(function () {
    seedCatalog();
});

/**
 * O vocabulário de quem julga um trabalho. Nenhuma rota, resposta ou classe da
 * aplicação pode falar essa língua.
 *
 * @return array<int, string>
 */
function judgingVocabulary(): array
{
    return ['score', 'grade', 'rating', 'evaluat', 'avalia', 'pontua', 'acerto', 'gabarito', 'corrig', 'feedback'];
}

test('no_route_scores_or_evaluates_a_diagram', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'url')->sole();

    $session = TrainingSession::factory()
        ->withProblem($problem)
        ->withDiagram(3, 2)
        ->create(['user_id' => $user->id]);

    foreach (Route::getRoutes() as $route) {
        foreach (judgingVocabulary() as $word) {
            expect(str($route->uri())->lower()->contains($word))->toBeFalse($route->uri())
                ->and(str((string) $route->getName())->lower()->contains($word))->toBeFalse($route->uri())
                ->and(str($route->getActionName())->lower()->contains($word))->toBeFalse($route->uri());
        }
    }

    $responses = [
        $this->actingAs($user)->get(route('board'))->assertOk()->viewData('page')['props'],
        $this->actingAs($user)->getJson(route('sessions.index'))->assertOk()->json(),
        $this->actingAs($user)->getJson(route('sessions.show', $session))->assertOk()->json(),
        $this->actingAs($user)->putJson(route('sessions.update', $session), autosaveBody())->assertOk()->json(),
    ];

    foreach ($responses as $payload) {
        foreach (judgingVocabulary() as $word) {
            expect(str(json_encode(keysOf($payload)))->lower()->contains($word))->toBeFalse($word);
        }
    }
});

test('no_class_of_the_application_compares_the_drawing_with_the_topics', function () {
    $files = Finder::create()->files()->in([app_path(), base_path('routes')])->name('*.php');

    foreach ($files as $file) {
        $source = str($file->getContents())->lower();

        foreach (judgingVocabulary() as $word) {
            expect($source->contains($word))->toBeFalse($file->getRelativePathname().' → '.$word);
        }
    }
});

test('the_topics_travel_untouched_from_the_seeder_to_the_catalog', function () {
    $problem = Problem::query()->with('topics')->where('slug', 'url')->sole();

    $brief = collect(
        $this->actingAs(User::factory()->create())
            ->get(route('board'))
            ->assertOk()
            ->viewData('page')['props']['catalog']['problems']
    )->firstWhere('slug', 'url');

    expect($brief['topics'])->toBe($problem->topics->pluck('content')->all())
        ->and($brief)->not->toHaveKey('topics_covered')
        ->and($brief)->not->toHaveKey('topics_missing');
});

/**
 * Todas as chaves de um payload, em qualquer profundidade.
 *
 * @param  array<mixed>  $payload
 * @return array<int, string>
 */
function keysOf(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        $keys[] = (string) $key;

        if (is_array($value)) {
            $keys = [...$keys, ...keysOf($value)];
        }
    }

    return $keys;
}
