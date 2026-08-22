<?php

use App\Models\Component;
use App\Models\ComponentCategory;
use Database\Seeders\CatalogSeeder;

beforeEach(fn () => $this->seed(CatalogSeeder::class));

test('seeds_six_categories_with_their_color_tokens', function () {
    expect(ComponentCategory::query()->get()->map->only(['slug', 'name', 'color_token'])->all())->toBe([
        ['slug' => 'client', 'name' => 'Cliente', 'color_token' => '--c-client'],
        ['slug' => 'edge', 'name' => 'Rede & Borda', 'color_token' => '--c-edge'],
        ['slug' => 'compute', 'name' => 'Computação', 'color_token' => '--c-compute'],
        ['slug' => 'data', 'name' => 'Dados', 'color_token' => '--c-data'],
        ['slug' => 'async', 'name' => 'Assíncrono', 'color_token' => '--c-async'],
        ['slug' => 'ops', 'name' => 'Operação', 'color_token' => '--c-ops'],
    ]);
});

test('seeds_twenty_eight_components_in_six_categories', function () {
    $perCategory = ComponentCategory::query()->get()
        ->mapWithKeys(fn (ComponentCategory $category): array => [
            $category->slug => $category->components()->count(),
        ])
        ->all();

    expect(Component::query()->count())->toBe(28)
        ->and($perCategory)->toBe([
            'client' => 3,
            'edge' => 5,
            'compute' => 7,
            'data' => 7,
            'async' => 3,
            'ops' => 3,
        ]);
});

test('components_keep_the_prototype_order_inside_each_category', function () {
    $slugsByCategory = Component::query()->with('componentCategory')->get()
        ->groupBy(fn (Component $component): string => $component->componentCategory->slug)
        ->map(fn ($components) => $components->sortBy('position')->pluck('slug')->all())
        ->all();

    expect($slugsByCategory)->toBe([
        'client' => ['browser', 'mobile', 'thirdparty'],
        'edge' => ['dns', 'cdn', 'waf', 'lb', 'gateway'],
        'compute' => ['api', 'graphql', 'grpc', 'service', 'worker', 'cron', 'ws'],
        'data' => ['sql', 'replica', 'nosql', 'cache', 'blob', 'search', 'warehouse'],
        'async' => ['queue', 'stream', 'dlq'],
        'ops' => ['auth', 'monitor', 'config'],
    ]);
});

test('dlq_has_distinct_short_name', function () {
    $dlq = Component::query()->where('slug', 'dlq')->sole();

    expect($dlq->name)->toBe('DLQ — fila de falhas')
        ->and($dlq->short_name)->toBe('DLQ');
});

test('only_dlq_shortens_its_label', function () {
    $shortened = Component::query()->get()
        ->filter(fn (Component $component): bool => $component->short_name !== $component->name)
        ->pluck('slug')
        ->all();

    expect($shortened)->toBe(['dlq']);
});

test('every_component_icon_key_exists_in_engine', function () {
    $engineKeys = canvasIconKeys();

    $missing = Component::query()->get()
        ->reject(fn (Component $component): bool => in_array($component->icon_key, $engineKeys, true))
        ->pluck('icon_key', 'slug')
        ->all();

    expect($engineKeys)->toHaveCount(28)
        ->and($missing)->toBe([]);
});
