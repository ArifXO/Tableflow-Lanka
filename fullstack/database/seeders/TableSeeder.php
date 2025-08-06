<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Table;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [
            ['number' => '01', 'seats' => 2, 'description' => 'Intimate table for two'],
            ['number' => '02', 'seats' => 2, 'description' => 'Cozy corner table'],
            ['number' => '03', 'seats' => 2, 'description' => 'Window-side table'],
            ['number' => '04', 'seats' => 2, 'description' => 'Quiet table for two'],

            ['number' => '05', 'seats' => 4, 'description' => 'Family table'],
            ['number' => '06', 'seats' => 4, 'description' => 'Central dining table'],
            ['number' => '07', 'seats' => 4, 'description' => 'Garden view table'],
            ['number' => '08', 'seats' => 4, 'description' => 'Standard four-seater'],

            ['number' => '09', 'seats' => 6, 'description' => 'Large family table'],
            ['number' => '10', 'seats' => 6, 'description' => 'Group dining table'],
            ['number' => '11', 'seats' => 6, 'description' => 'Extended family table'],
            ['number' => '12', 'seats' => 6, 'description' => 'Celebration table'],

            ['number' => '13', 'seats' => 8, 'description' => 'Large group table'],
            ['number' => '14', 'seats' => 8, 'description' => 'Party table'],
            ['number' => '15', 'seats' => 8, 'description' => 'Event table'],
            ['number' => '16', 'seats' => 8, 'description' => 'VIP table'],
        ];

        foreach ($tables as $tableData) {
            Table::create($tableData);
        }
    }
}
