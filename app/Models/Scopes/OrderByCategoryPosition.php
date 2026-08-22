<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A paleta de componentes é lida categoria a categoria, e dentro de cada uma
 * na ordem do protótipo.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class OrderByCategoryPosition implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->orderBy($model->qualifyColumn('component_category_id'))
            ->orderBy($model->qualifyColumn('position'));
    }
}
