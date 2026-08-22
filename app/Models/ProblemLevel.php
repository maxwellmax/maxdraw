<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\ProblemLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Problem> $problems
 */
#[Fillable(['name', 'slug', 'position', 'is_active'])]
#[ScopedBy(OrderByPosition::class)]
class ProblemLevel extends Model
{
    /** @use HasFactory<ProblemLevelFactory> */
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
     * @return HasMany<Problem, $this>
     */
    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }
}
