<?php

namespace Database\Seeders;

use App\Models\SequenceMode;
use Illuminate\Database\Seeder;

/**
 * `position` é a ordem do ciclo do botão de numeração: out → flow → off.
 */
class SequenceModeSeeder extends Seeder
{
    public function run(): void
    {
        SequenceMode::upsert([
            [
                'slug' => 'out',
                'name' => 'Ordem de saída de cada bloco',
                'legend_text' => 'quando um bloco dispara mais de uma coisa, o número diz o que vem antes',
                'position' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'flow',
                'name' => 'Sequência do fluxo inteiro',
                'legend_text' => 'os passos na ordem em que acontecem, começando pelo cliente',
                'position' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'off',
                'name' => 'Sem números',
                'legend_text' => '',
                'position' => 3,
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'legend_text', 'position', 'is_active']);
    }
}
