<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByPosition;
use Carbon\CarbonImmutable;
use Database\Factories\LinkTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * O tipo de ligação é referenciado por slug de dentro da coluna JSON
 * `training_sessions.edges` (`edges[].kind`), e por isso não tem relacionamento.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $badge_label
 * @property string|null $dash_array
 * @property bool $is_bidirectional_default
 * @property string $gloss
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'badge_label', 'dash_array', 'is_bidirectional_default', 'gloss', 'position', 'is_active'])]
#[ScopedBy(OrderByPosition::class)]
class LinkType extends Model
{
    /** @use HasFactory<LinkTypeFactory> */
    use HasActiveScope, HasFactory, ReadOnlyAtRuntime;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bidirectional_default' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
