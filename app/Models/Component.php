<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use App\Models\Scopes\OrderByCategoryPosition;
use Carbon\CarbonImmutable;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cor do bloco vem sempre da categoria — o componente não tem coluna de cor.
 *
 * @property int $id
 * @property int $component_category_id
 * @property string $slug
 * @property string $name
 * @property string $short_name
 * @property string $icon_key
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ComponentCategory $componentCategory
 */
#[Fillable(['component_category_id', 'slug', 'name', 'short_name', 'icon_key', 'position', 'is_active'])]
#[ScopedBy(OrderByCategoryPosition::class)]
class Component extends Model
{
    /** @use HasFactory<ComponentFactory> */
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
     * @return BelongsTo<ComponentCategory, $this>
     */
    public function componentCategory(): BelongsTo
    {
        return $this->belongsTo(ComponentCategory::class);
    }
}
