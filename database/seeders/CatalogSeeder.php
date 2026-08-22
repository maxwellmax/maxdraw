<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Raiz do catálogo versionado: editar o catálogo é editar estes seeders, nunca
 * uma rota ou tela. A ordem abaixo é a de dependência — os lookups primeiro,
 * depois quem os referencia por slug.
 */
class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProblemLevelSeeder::class,
            ProblemItemTypeSeeder::class,
            SequenceModeSeeder::class,
            SessionDurationSeeder::class,
            EstimateModeSeeder::class,
            LinkTypeSeeder::class,
            ComponentCategorySeeder::class,
            ComponentSeeder::class,
            PhaseSeeder::class,
            ChecklistItemSeeder::class,
            ProblemSeeder::class,
        ]);
    }
}
