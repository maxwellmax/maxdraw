<?php

namespace App\Models;

use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\ProblemItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $problem_id
 * @property int $problem_item_type_id
 * @property int $position
 * @property string $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Problem $problem
 * @property-read ProblemItemType $problemItemType
 */
#[Fillable(['problem_id', 'problem_item_type_id', 'position', 'content'])]
#[ScopedBy(OrderByPosition::class)]
class ProblemItem extends Model
{
    /** @use HasFactory<ProblemItemFactory> */
    use HasFactory, ReadOnlyAtRuntime;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Problem, $this>
     */
    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    /**
     * @return BelongsTo<ProblemItemType, $this>
     */
    public function problemItemType(): BelongsTo
    {
        return $this->belongsTo(ProblemItemType::class);
    }
}
