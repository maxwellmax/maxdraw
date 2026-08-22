<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A ordem do catálogo é conteúdo, não acaso: toda leitura sai por `position`
 * sem que o chamador precise lembrar disso.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class OrderByPosition implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->orderBy($model->qualifyColumn('position'));
    }
}
