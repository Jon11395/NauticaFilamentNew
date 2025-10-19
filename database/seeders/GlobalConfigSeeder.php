<?php

namespace Database\Seeders;

use App\Models\GlobalConfig;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GlobalConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed default global configurations
        GlobalConfig::setValue(
            'night_work_bonus',
            8300,
            'integer',
            'Monto en colones por día de trabajo nocturno'
        );
    }
}
