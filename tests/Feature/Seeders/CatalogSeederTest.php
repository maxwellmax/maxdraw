<?php

use App\Models\ChecklistItem;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\Phase;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

test('catalog_seeder_is_idempotent', function () {
    $this->seed(CatalogSeeder::class);

    $firstRun = catalogSnapshot();

    $this->seed(CatalogSeeder::class);

    expect(catalogSnapshot())->toEqual($firstRun);
});

test('catalog_seeder_resolves_relations_by_slug_not_by_fixed_ids', function () {
    DB::table('component_categories')->insert([
        'name' => 'placeholder', 'slug' => 'placeholder', 'color_token' => '--c-placeholder',
        'position' => 99, 'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('phases')->insert([
        'slug' => 'placeholder', 'name' => 'placeholder', 'weight' => '0.000',
        'position' => 99, 'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(CatalogSeeder::class);

    $async = ComponentCategory::query()->where('slug', 'async')->sole();
    $scale = Phase::query()->where('slug', 'escala-trade-offs')->sole();

    expect(Component::query()->where('slug', 'dlq')->sole()->component_category_id)->toBe($async->id)
        ->and($scale->checklistItems)->toHaveCount(6);
});

test('catalog_tables_are_read_only_at_runtime', function () {
    $this->seed(CatalogSeeder::class);

    $beforeRoutes = catalogSnapshot();

    $user = User::factory()->create();

    foreach (Route::getRoutes() as $route) {
        if (str_contains($route->uri(), '{')) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            try {
                $this->actingAs($user)->call($method, $route->uri());
            } catch (Throwable) {
                continue;
            }
        }
    }

    expect(catalogSnapshot())->toEqual($beforeRoutes);

    asHttpRuntime(function () {
        foreach (catalogModels() as $model) {
            $record = $model::query()->first();

            expect(fn () => $record->replicate()->save())->toThrow(RuntimeException::class)
                ->and(fn () => $record->update(['is_active' => false]))->toThrow(RuntimeException::class)
                ->and(fn () => $record->delete())->toThrow(RuntimeException::class);
        }
    });

    expect(catalogSnapshot())->toEqual($beforeRoutes);
});

test('catalog_seed_counts_match_the_prototype', function () {
    $this->seed(CatalogSeeder::class);

    expect([
        ProblemLevel::query()->count(),
        ProblemItemType::query()->count(),
        SequenceMode::query()->count(),
        SessionDuration::query()->count(),
        EstimateMode::query()->count(),
        LinkType::query()->count(),
        ComponentCategory::query()->count(),
        Component::query()->count(),
        Phase::query()->count(),
        ChecklistItem::query()->count(),
    ])->toBe([3, 3, 3, 3, 2, 9, 6, 28, 5, 25]);
});
