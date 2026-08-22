<?php

namespace Database\Seeders;

use App\Models\ComponentCategory;
use Illuminate\Database\Seeder;

/**
 * `color_token` é a única fonte de cor do sistema — nenhuma outra tabela do
 * catálogo tem coluna de cor.
 */
class ComponentCategorySeeder extends Seeder
{
    public function run(): void
    {
        ComponentCategory::upsert([
            ['slug' => 'client', 'name' => 'Cliente', 'color_token' => '--c-client', 'position' => 1, 'is_active' => true],
            ['slug' => 'edge', 'name' => 'Rede & Borda', 'color_token' => '--c-edge', 'position' => 2, 'is_active' => true],
            ['slug' => 'compute', 'name' => 'Computação', 'color_token' => '--c-compute', 'position' => 3, 'is_active' => true],
            ['slug' => 'data', 'name' => 'Dados', 'color_token' => '--c-data', 'position' => 4, 'is_active' => true],
            ['slug' => 'async', 'name' => 'Assíncrono', 'color_token' => '--c-async', 'position' => 5, 'is_active' => true],
            ['slug' => 'ops', 'name' => 'Operação', 'color_token' => '--c-ops', 'position' => 6, 'is_active' => true],
        ], ['slug'], ['name', 'color_token', 'position', 'is_active']);
    }
}
