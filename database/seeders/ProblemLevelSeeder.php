<?php

namespace Database\Seeders;

use App\Models\ProblemLevel;
use Illuminate\Database\Seeder;

class ProblemLevelSeeder extends Seeder
{
    public function run(): void
    {
        ProblemLevel::upsert([
            ['slug' => 'base', 'name' => 'Base', 'position' => 1, 'is_active' => true],
            ['slug' => 'intermediate', 'name' => 'Intermediário', 'position' => 2, 'is_active' => true],
            ['slug' => 'advanced', 'name' => 'Avançado', 'position' => 3, 'is_active' => true],
        ], ['slug'], ['name', 'position', 'is_active']);
    }
}
