<?php

namespace Database\Seeders;

use App\Models\ProblemItemType;
use Illuminate\Database\Seeder;

class ProblemItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        ProblemItemType::upsert([
            [
                'slug' => 'requirement',
                'name' => 'Requisito funcional',
                'description' => 'O que o sistema faz, listado antes de qualquer desenho',
                'is_active' => true,
            ],
            [
                'slug' => 'scale',
                'name' => 'Escala alvo',
                'description' => 'Os números que o desenho precisa aguentar',
                'is_active' => true,
            ],
            [
                'slug' => 'topic',
                'name' => 'Tópico cobrado',
                'description' => 'O gabarito — só se abre depois de terminar',
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'description', 'is_active']);
    }
}
