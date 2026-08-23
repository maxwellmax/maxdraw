<?php

use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\SessionDuration;
use Database\Seeders\CatalogSeeder;

beforeEach(fn () => $this->seed(CatalogSeeder::class));

test('problem_levels_seed_matches_prototype', function () {
    expect(ProblemLevel::query()->get()->map->only(['slug', 'name', 'position'])->all())->toBe([
        ['slug' => 'base', 'name' => 'Base', 'position' => 1],
        ['slug' => 'intermediate', 'name' => 'Intermediário', 'position' => 2],
        ['slug' => 'advanced', 'name' => 'Avançado', 'position' => 3],
    ]);
});

test('problem_item_types_seed_the_three_lists', function () {
    expect(ProblemItemType::query()->pluck('name', 'slug')->all())->toBe([
        'requirement' => 'Requisito funcional',
        'scale' => 'Escala alvo',
        'topic' => 'Tópico cobrado',
    ]);
});

test('link_types_seed_matches_prototype', function () {
    $seeded = LinkType::query()->get()
        ->map->only(['slug', 'badge_label', 'dash_array', 'is_bidirectional_default'])
        ->all();

    expect($seeded)->toBe([
        ['slug' => 'http', 'badge_label' => 'HTTP', 'dash_array' => null, 'is_bidirectional_default' => false],
        ['slug' => 'grpc', 'badge_label' => 'gRPC', 'dash_array' => null, 'is_bidirectional_default' => false],
        ['slug' => 'ws', 'badge_label' => 'WS', 'dash_array' => null, 'is_bidirectional_default' => true],
        ['slug' => 'event', 'badge_label' => 'async', 'dash_array' => '5 4.5', 'is_bidirectional_default' => false],
        ['slug' => 'query', 'badge_label' => 'query', 'dash_array' => null, 'is_bidirectional_default' => false],
        ['slug' => 'cache', 'badge_label' => 'cache', 'dash_array' => null, 'is_bidirectional_default' => false],
        ['slug' => 'repl', 'badge_label' => 'replica', 'dash_array' => '5 4.5', 'is_bidirectional_default' => false],
        ['slug' => 'batch', 'badge_label' => 'batch', 'dash_array' => '5 4.5', 'is_bidirectional_default' => false],
        ['slug' => 'retry', 'badge_label' => 'retry', 'dash_array' => '2 4.5', 'is_bidirectional_default' => false],
    ]);
});

test('every_link_type_carries_its_teaching_gloss', function () {
    expect(LinkType::query()->pluck('gloss', 'slug')->all())->toBe([
        'http' => 'requisição e resposta; o chamador fica esperando',
        'grpc' => 'RPC binário entre serviços, com contrato tipado',
        'ws' => 'canal aberto nos dois sentidos; o servidor empurra',
        'event' => 'o produtor não espera resposta; entrega desacoplada',
        'query' => 'leitura ou escrita no banco; conta no QPS dele',
        'cache' => 'consulta antes do banco; pode dar miss',
        'repl' => 'cópia do dado para outro nó; há atraso de replicação',
        'batch' => 'volume grande em janelas; fora do caminho do usuário',
        'retry' => 'estourou as tentativas e foi para a DLQ; ninguém consumiu',
    ]);
});

test('exactly_one_default_duration', function () {
    expect(SessionDuration::query()->pluck('minutes')->all())->toBe([30, 45, 60])
        ->and(SessionDuration::query()->where('is_default', true)->pluck('minutes')->all())->toBe([45]);
});

test('estimate_modes_highlight_the_matching_output_row', function () {
    expect(EstimateMode::query()->get()->map->only(['slug', 'name', 'highlighted_row'])->all())->toBe([
        ['slug' => 'user', 'name' => 'Por usuários', 'highlighted_row' => 'Escritas por dia'],
        ['slug' => 'month', 'name' => 'Por volume mensal', 'highlighted_row' => 'Escritas por mês'],
    ]);
});
