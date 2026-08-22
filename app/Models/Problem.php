<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\ProblemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $tag
 * @property int $problem_level_id
 * @property string $context
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProblemLevel $problemLevel
 * @property-read Collection<int, ProblemItem> $problemItems
 * @property-read Collection<int, ProblemItem> $requirements
 * @property-read Collection<int, ProblemItem> $scaleTargets
 * @property-read Collection<int, ProblemItem> $topics
 * @property-read Collection<int, TrainingSession> $trainingSessions
 */
#[Fillable(['slug', 'name', 'tag', 'problem_level_id', 'context', 'position', 'is_active'])]
#[ScopedBy(OrderByPosition::class)]
class Problem extends Model
{
    /** @use HasFactory<ProblemFactory> */
    use HasActiveScope, HasFactory, ReadOnlyAtRuntime;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProblemLevel, $this>
     */
    public function problemLevel(): BelongsTo
    {
        return $this->belongsTo(ProblemLevel::class);
    }

    /**
     * As três listas do enunciado vivem nesta relação, separadas pelo tipo.
     * A ordem por `position` vem do escopo global de `ProblemItem`.
     *
     * @return HasMany<ProblemItem, $this>
     */
    public function problemItems(): HasMany
    {
        return $this->hasMany(ProblemItem::class);
    }

    /**
     * @return HasMany<ProblemItem, $this>
     */
    public function requirements(): HasMany
    {
        return $this->itemsOfType('requirement');
    }

    /**
     * @return HasMany<ProblemItem, $this>
     */
    public function scaleTargets(): HasMany
    {
        return $this->itemsOfType('scale');
    }

    /**
     * @return HasMany<ProblemItem, $this>
     */
    public function topics(): HasMany
    {
        return $this->itemsOfType('topic');
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * @return HasMany<ProblemItem, $this>
     */
    private function itemsOfType(string $slug): HasMany
    {
        return $this->problemItems()->whereRelation('problemItemType', 'slug', $slug);
    }
}
