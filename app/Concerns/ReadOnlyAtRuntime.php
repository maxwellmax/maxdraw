<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait ReadOnlyAtRuntime
{
    /**
     * O catálogo é populado pelo seeder versionado e apenas lido pela aplicação.
     * O guard vale para o runtime HTTP; console (seeder, migrations, suíte de
     * testes) continua escrevendo, que é exatamente quem tem esse direito.
     */
    public static function bootReadOnlyAtRuntime(): void
    {
        $guard = static function (Model $model): void {
            if (app()->runningInConsole()) {
                return;
            }

            throw new RuntimeException(sprintf(
                'O catálogo é somente-leitura em runtime: [%s] só pode ser escrito pelo seeder.',
                $model::class,
            ));
        };

        static::saving($guard);
        static::deleting($guard);
    }
}
