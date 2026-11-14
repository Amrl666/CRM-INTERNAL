<?php

namespace Database\Seeders;

use App\Domain\Deals\Models\Pipeline;
use App\Domain\Deals\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = Pipeline::firstOrCreate(['name' => 'Sales Pipeline'], ['is_default' => true]);

        $stages = [
            ['name' => 'Baru', 'position' => 1, 'probability' => 10],
            ['name' => 'Kualifikasi', 'position' => 2, 'probability' => 30],
            ['name' => 'Proposal', 'position' => 3, 'probability' => 50],
            ['name' => 'Negosiasi', 'position' => 4, 'probability' => 70],
            ['name' => 'Menang', 'position' => 5, 'probability' => 100, 'is_won' => true],
            ['name' => 'Kalah', 'position' => 6, 'probability' => 0, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            PipelineStage::firstOrCreate(
                ['pipeline_id' => $pipeline->id, 'position' => $stage['position']],
                $stage + ['pipeline_id' => $pipeline->id]
            );
        }
    }
}
