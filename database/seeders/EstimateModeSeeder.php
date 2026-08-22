<?php

namespace Database\Seeders;

use App\Models\EstimateMode;
use Illuminate\Database\Seeder;

class EstimateModeSeeder extends Seeder
{
    public function run(): void
    {
        EstimateMode::upsert([
            [
                'slug' => 'user',
                'name' => 'Por usuários',
                'highlighted_row' => 'Escritas por dia',
                'position' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'month',
                'name' => 'Por volume mensal',
                'highlighted_row' => 'Escritas por mês',
                'position' => 2,
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'highlighted_row', 'position', 'is_active']);
    }
}
