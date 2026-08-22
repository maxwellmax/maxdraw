<?php

use App\Models\ChecklistItem;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\Phase;
use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\TrainingSession;

test('problem_exposes_three_ordered_lists', function () {
    $problem = Problem::factory()->create();
    $types = collect(['requirement', 'scale', 'topic'])
        ->mapWithKeys(fn (string $slug): array => [$slug => ProblemItemType::factory()->create(['slug' => $slug])]);

    foreach ($types as $slug => $type) {
        foreach ([3, 1, 2] as $position) {
            ProblemItem::factory()->create([
                'problem_id' => $problem->id,
                'problem_item_type_id' => $type->id,
                'position' => $position,
                'content' => "{$slug}-{$position}",
            ]);
        }
    }

    ProblemItem::factory()->create(['problem_item_type_id' => $types['requirement']->id]);

    expect($problem->requirements->pluck('content')->all())->toBe(['requirement-1', 'requirement-2', 'requirement-3'])
        ->and($problem->scaleTargets->pluck('content')->all())->toBe(['scale-1', 'scale-2', 'scale-3'])
        ->and($problem->topics->pluck('content')->all())->toBe(['topic-1', 'topic-2', 'topic-3'])
        ->and($problem->problemItems)->toHaveCount(9);
});

test('phase_weights_sum_to_one', function () {
    foreach ([0.110, 0.110, 0.180, 0.270, 0.330] as $position => $weight) {
        Phase::factory()->create(['weight' => $weight, 'position' => $position + 1]);
    }

    $sum = Phase::query()->get()->reduce(
        fn (string $carry, Phase $phase): string => bcadd($carry, $phase->weight, 3),
        '0.000',
    );

    expect(Phase::query()->count())->toBe(5)
        ->and($sum)->toBe('1.000');
});

test('problem_items_are_always_read_in_position_order', function () {
    $problem = Problem::factory()->create();
    $type = ProblemItemType::factory()->create();

    foreach ([5, 1, 4, 2] as $position) {
        ProblemItem::factory()->create([
            'problem_id' => $problem->id,
            'problem_item_type_id' => $type->id,
            'position' => $position,
        ]);
    }

    expect(ProblemItem::query()->pluck('position')->all())->toBe([1, 2, 4, 5])
        ->and($problem->problemItems->pluck('position')->all())->toBe([1, 2, 4, 5]);
});

test('problem_item_belongs_to_problem_and_type', function () {
    $item = ProblemItem::factory()->create();

    expect($item->problem)->toBeInstanceOf(Problem::class)
        ->and($item->problemItemType)->toBeInstanceOf(ProblemItemType::class)
        ->and($item->position)->toBeInt();
});

test('problem_belongs_to_level_and_has_many_training_sessions', function () {
    $level = ProblemLevel::factory()->create();
    $problem = Problem::factory()->create(['problem_level_id' => $level->id]);
    TrainingSession::factory()->count(2)->withProblem($problem)->create();
    TrainingSession::factory()->create();

    expect($problem->problemLevel->id)->toBe($level->id)
        ->and($problem->trainingSessions)->toHaveCount(2);
});

test('components_are_read_by_category_then_position', function () {
    $first = ComponentCategory::factory()->create(['position' => 1]);
    $second = ComponentCategory::factory()->create(['position' => 2]);

    $secondCategoryComponent = Component::factory()->create(['component_category_id' => $second->id, 'position' => 1, 'slug' => 'b1']);
    $firstCategorySecond = Component::factory()->create(['component_category_id' => $first->id, 'position' => 2, 'slug' => 'a2']);
    $firstCategoryFirst = Component::factory()->create(['component_category_id' => $first->id, 'position' => 1, 'slug' => 'a1']);

    expect(Component::query()->pluck('slug')->all())->toBe(['a1', 'a2', 'b1'])
        ->and($firstCategoryFirst->componentCategory->id)->toBe($first->id)
        ->and($first->components->pluck('slug')->all())->toBe(['a1', 'a2'])
        ->and($second->components->pluck('slug')->all())->toBe([$secondCategoryComponent->slug])
        ->and($firstCategorySecond->componentCategory->color_token)->toBe($first->color_token);
});

test('phase_has_many_checklist_items_in_position_order', function () {
    $phase = Phase::factory()->create();

    foreach ([3, 1, 2] as $position) {
        ChecklistItem::factory()->create([
            'phase_id' => $phase->id,
            'position' => $position,
            'content' => "item-{$position}",
        ]);
    }

    ChecklistItem::factory()->create();

    expect($phase->checklistItems->pluck('content')->all())->toBe(['item-1', 'item-2', 'item-3'])
        ->and($phase->checklistItems->first()->phase->id)->toBe($phase->id)
        ->and($phase->weight)->toBeString();
});

test('deleting_a_phase_deletes_its_checklist_items', function () {
    $phase = Phase::factory()->create();
    ChecklistItem::factory()->count(3)->create(['phase_id' => $phase->id]);

    $phase->delete();

    expect(ChecklistItem::query()->count())->toBe(0);
});

test('catalog_active_scope_filters_inactive', function (string $model) {
    $active = $model::factory()->create(['is_active' => true]);
    $model::factory()->create(['is_active' => false]);

    $found = $model::query()->active()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->id)->toBe($active->id);
})->with([
    'problems' => [Problem::class],
    'components' => [Component::class],
    'phases' => [Phase::class],
    'checklist_items' => [ChecklistItem::class],
]);

test('catalog_models_reject_writes_at_runtime', function (string $model) {
    $record = $model::factory()->create();

    asHttpRuntime(function () use ($model, $record) {
        expect(fn () => $model::factory()->create())->toThrow(RuntimeException::class)
            ->and(fn () => $record->update(['position' => 99]))->toThrow(RuntimeException::class)
            ->and(fn () => $record->delete())->toThrow(RuntimeException::class);
    });

    expect($model::query()->where('id', $record->id)->exists())->toBeTrue();
})->with([
    'problems' => [Problem::class],
    'problem_items' => [ProblemItem::class],
    'components' => [Component::class],
    'phases' => [Phase::class],
    'checklist_items' => [ChecklistItem::class],
]);
