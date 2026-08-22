<?php

use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use App\Models\TrainingSession;

test('lookup_active_scope_filters_inactive', function (string $model) {
    $active = $model::factory()->create(['is_active' => true]);
    $inactive = $model::factory()->create(['is_active' => false]);

    $found = $model::query()->active()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->id)->toBe($active->id)
        ->and($found->pluck('id')->all())->not->toContain($inactive->id);
})->with('lookupModels');

test('session_duration_default_is_unique', function () {
    SessionDuration::factory()->create(['minutes' => 30, 'position' => 1]);
    SessionDuration::factory()->default()->create(['position' => 2]);
    SessionDuration::factory()->create(['minutes' => 60, 'position' => 3]);

    $defaults = SessionDuration::query()->where('is_default', true)->get();

    expect($defaults)->toHaveCount(1)
        ->and($defaults->first()->minutes)->toBe(45);
});

test('lookup_models_cast_their_columns', function (string $model, array $casts) {
    $record = $model::factory()->create();

    $fresh = $model::query()->findOrFail($record->id);

    foreach ($casts as $attribute => $type) {
        expect(gettype($fresh->{$attribute}))->toBe($type);
    }
})->with([
    'problem_levels' => [ProblemLevel::class, ['position' => 'integer', 'is_active' => 'boolean']],
    'problem_item_types' => [ProblemItemType::class, ['is_active' => 'boolean']],
    'component_categories' => [ComponentCategory::class, ['position' => 'integer', 'is_active' => 'boolean']],
    'link_types' => [LinkType::class, ['position' => 'integer', 'is_active' => 'boolean', 'is_bidirectional_default' => 'boolean']],
    'sequence_modes' => [SequenceMode::class, ['position' => 'integer', 'is_active' => 'boolean']],
    'session_durations' => [SessionDuration::class, ['minutes' => 'integer', 'position' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean']],
    'estimate_modes' => [EstimateMode::class, ['position' => 'integer', 'is_active' => 'boolean']],
]);

test('lookup_models_are_read_by_position', function (string $model) {
    $last = $model::factory()->create(['position' => 30]);
    $first = $model::factory()->create(['position' => 10]);
    $middle = $model::factory()->create(['position' => 20]);

    expect($model::query()->pluck('id')->all())->toBe([$first->id, $middle->id, $last->id]);
})->with([
    'problem_levels' => [ProblemLevel::class],
    'component_categories' => [ComponentCategory::class],
    'link_types' => [LinkType::class],
    'sequence_modes' => [SequenceMode::class],
    'session_durations' => [SessionDuration::class],
    'estimate_modes' => [EstimateMode::class],
]);

test('problem_level_has_many_problems', function () {
    $level = ProblemLevel::factory()->create();
    Problem::factory()->count(2)->create(['problem_level_id' => $level->id]);
    Problem::factory()->create();

    expect($level->problems)->toHaveCount(2)
        ->and($level->problems->first()->problemLevel->id)->toBe($level->id);
});

test('problem_item_type_has_many_problem_items', function () {
    $type = ProblemItemType::factory()->create();
    ProblemItem::factory()->count(3)->create(['problem_item_type_id' => $type->id]);
    ProblemItem::factory()->create();

    expect($type->problemItems)->toHaveCount(3);
});

test('component_category_has_many_components', function () {
    $category = ComponentCategory::factory()->create();
    $category->components()->saveMany(Component::factory()->count(2)->make());
    Component::factory()->create();

    expect($category->fresh()->components)->toHaveCount(2);
});

test('session_duration_and_sequence_mode_have_many_training_sessions', function () {
    $duration = SessionDuration::factory()->default()->create();
    $mode = SequenceMode::factory()->create(['slug' => 'out']);

    TrainingSession::factory()->count(2)->create();

    expect($duration->trainingSessions)->toHaveCount(2)
        ->and($mode->trainingSessions)->toHaveCount(2);
});

test('lookup_models_reject_writes_at_runtime', function (string $model) {
    $record = $model::factory()->create();

    asHttpRuntime(function () use ($model, $record) {
        expect(fn () => $model::factory()->create())->toThrow(RuntimeException::class)
            ->and(fn () => $record->update(['is_active' => false]))->toThrow(RuntimeException::class)
            ->and(fn () => $record->delete())->toThrow(RuntimeException::class);
    });

    expect($model::query()->count())->toBe(1);
})->with('lookupModels');
