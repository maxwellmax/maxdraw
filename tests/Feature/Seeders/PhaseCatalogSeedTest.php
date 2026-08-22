<?php

use App\Models\ChecklistItem;
use App\Models\Phase;
use Database\Seeders\CatalogSeeder;

beforeEach(fn () => $this->seed(CatalogSeeder::class));

test('seeds_five_phases_with_weights_summing_to_one', function () {
    $phases = Phase::query()->get();

    $sum = $phases->reduce(
        fn (string $carry, Phase $phase): string => bcadd($carry, $phase->weight, 3),
        '0.000',
    );

    expect($phases->map->only(['name', 'weight', 'position'])->all())->toBe([
        ['name' => 'Requisitos & escopo', 'weight' => '0.110', 'position' => 1],
        ['name' => 'Estimativas de capacidade', 'weight' => '0.110', 'position' => 2],
        ['name' => 'API & modelo de dados', 'weight' => '0.180', 'position' => 3],
        ['name' => 'Desenho de alto nível', 'weight' => '0.270', 'position' => 4],
        ['name' => 'Escala & trade-offs', 'weight' => '0.330', 'position' => 5],
    ])->and($sum)->toBe('1.000');
});

test('seeds_twenty_five_checklist_items_distributed_4_5_5_5_6', function () {
    $perPhase = Phase::query()->get()
        ->mapWithKeys(fn (Phase $phase): array => [$phase->slug => $phase->checklistItems()->count()])
        ->all();

    expect(ChecklistItem::query()->count())->toBe(25)
        ->and($perPhase)->toBe([
            'requisitos-escopo' => 4,
            'estimativas-de-capacidade' => 5,
            'api-modelo-de-dados' => 5,
            'desenho-de-alto-nivel' => 5,
            'escala-trade-offs' => 6,
        ]);
});

test('checklist_positions_are_sequential_inside_each_phase', function () {
    foreach (Phase::query()->get() as $phase) {
        $positions = $phase->checklistItems->pluck('position')->all();

        expect($positions)->toBe(range(1, count($positions)));
    }
});

test('checklist_items_keep_the_prototype_wording', function () {
    $contentsByPhase = Phase::query()->get()
        ->mapWithKeys(fn (Phase $phase): array => [
            $phase->slug => $phase->checklistItems->pluck('content')->all(),
        ])
        ->all();

    expect($contentsByPhase)->toBe([
        'requisitos-escopo' => [
            'Requisitos funcionais listados — o que o sistema FAZ',
            'Fora de escopo declarado em voz alta',
            'Não-funcionais: latência, disponibilidade, consistência',
            'Perfil de uso: leitura pesada ou escrita pesada?',
        ],
        'estimativas-de-capacidade' => [
            'Usuários ativos por dia e ações por usuário',
            'QPS médio e QPS de pico',
            'Armazenamento por dia, por ano e na retenção',
            'Banda de entrada e de saída',
            'Conclusão: o que essa escala obriga (cache? shard? CDN?)',
        ],
        'api-modelo-de-dados' => [
            'Endpoints principais com entrada e saída',
            'Entidades e relacionamentos',
            'Escolha do banco justificada, não presumida',
            'Chave de particionamento definida',
            'Índices necessários para as consultas do produto',
        ],
        'desenho-de-alto-nivel' => [
            'Caminho de ESCRITA desenhado ponta a ponta',
            'Caminho de LEITURA desenhado ponta a ponta',
            'Síncrono e assíncrono separados no desenho',
            'Onde entra cache e o que invalida cada entrada',
            'Fluxo narrado do clique até a resposta',
        ],
        'escala-trade-offs' => [
            'Gargalo principal nomeado',
            'Estratégia de sharding e de replicação',
            'Nenhum ponto único de falha sobrou',
            'Consistência forte ou eventual — e o porquê',
            'Retry, idempotência e backpressure',
            'O que você mediria em produção',
        ],
    ]);
});
