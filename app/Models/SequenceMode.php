<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\SequenceModeFactory;
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
 * @property string $legend_text
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, TrainingSession> $trainingSessions
 */
#[Fillable(['name', 'slug', 'legend_text', 'position', 'is_active'])]
#[ScopedBy(OrderByPosition::class)]
class SequenceMode extends Model
{
    /** @use HasFactory<SequenceModeFactory> */
    use HasActiveScope, HasFactory, ReadOnlyAtRuntime;

    /**
     * O modo com que a sessão nasce, e para o qual um valor inválido de
     * `seq_mode` é normalizado em vez de recusar a gravação (US-4.3).
     */
    public const DEFAULT_SLUG = 'out';

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
     * @return HasMany<TrainingSession, $this>
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
