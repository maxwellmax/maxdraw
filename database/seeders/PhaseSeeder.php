<?php

namespace Database\Seeders;

use App\Models\Phase;
use Illuminate\Database\Seeder;

/**
 * Os cinco pesos somam exatamente 1.000 — é essa soma que faz o progresso do
 * roteiro fechar em 100%.
 */
class PhaseSeeder extends Seeder
{
    public function run(): void
    {
        Phase::upsert([
            ['slug' => 'requisitos-escopo', 'name' => 'Requisitos & escopo', 'weight' => '0.110', 'position' => 1, 'is_active' => true],
            ['slug' => 'estimativas-de-capacidade', 'name' => 'Estimativas de capacidade', 'weight' => '0.110', 'position' => 2, 'is_active' => true],
            ['slug' => 'api-modelo-de-dados', 'name' => 'API & modelo de dados', 'weight' => '0.180', 'position' => 3, 'is_active' => true],
            ['slug' => 'desenho-de-alto-nivel', 'name' => 'Desenho de alto nível', 'weight' => '0.270', 'position' => 4, 'is_active' => true],
            ['slug' => 'escala-trade-offs', 'name' => 'Escala & trade-offs', 'weight' => '0.330', 'position' => 5, 'is_active' => true],
        ], ['slug'], ['name', 'weight', 'position', 'is_active']);
    }
}
