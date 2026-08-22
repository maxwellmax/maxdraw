<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasActiveScope
{
    /**
     * Item de catálogo desativado continua renderizando o que já foi desenhado,
     * mas some da paleta e dos menus — por isso a leitura de UI passa por aqui.
     *
     * @param  Builder<covariant Model>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }
}
