<?php

namespace App\Models;

use App\Concerns\HasActiveScope;
use App\Concerns\ReadOnlyAtRuntime;
use Carbon\CarbonImmutable;
use Database\Factories\ProblemItemTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ProblemItem> $problemItems
 */
#[Fillable(['name', 'slug', 'description', 'is_active'])]
class ProblemItemType extends Model
{
    /** @use HasFactory<ProblemItemTypeFactory> */
    use HasActiveScope, HasFactory, ReadOnlyAtRuntime;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProblemItem, $this>
     */
    public function problemItems(): HasMany
    {
        return $this->hasMany(ProblemItem::class);
    }
}
