<?php

namespace Database\Seeders;

use App\Models\SessionDuration;
use Illuminate\Database\Seeder;

class SessionDurationSeeder extends Seeder
{
    public function run(): void
    {
        SessionDuration::upsert([
            ['minutes' => 30, 'is_default' => false, 'position' => 1, 'is_active' => true],
            ['minutes' => 45, 'is_default' => true, 'position' => 2, 'is_active' => true],
            ['minutes' => 60, 'is_default' => false, 'position' => 3, 'is_active' => true],
        ], ['minutes'], ['is_default', 'position', 'is_active']);
    }
}
