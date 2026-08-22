<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\EstimateModeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * O modo de estimativa é referenciado por slug de dentro da coluna JSON
 * `training_sessions.estimate` (`estimate.mode`), e por isso não tem relacionamento.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $highlighted_row
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'highlighted_row', 'position', 'is_active'])]
#[ScopedBy(OrderByPosition::class)]
class EstimateMode extends Model
{
    /** @use HasFactory<EstimateModeFactory> */
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
}
