<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['table_number' => 'T-01', 'capacity' => 2, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-02', 'capacity' => 2, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-03', 'capacity' => 2, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-04', 'capacity' => 2, 'location' => 'Outdoor Dining'],

            ['table_number' => 'T-05', 'capacity' => 4, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-06', 'capacity' => 4, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-07', 'capacity' => 4, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-08', 'capacity' => 4, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-09', 'capacity' => 4, 'location' => 'Outdoor Dining'],
            ['table_number' => 'T-10', 'capacity' => 4, 'location' => 'Outdoor Dining'],

            ['table_number' => 'T-11', 'capacity' => 6, 'location' => 'Indoor Dining'],
            ['table_number' => 'T-12', 'capacity' => 6, 'location' => 'Outdoor Dining'],
        ];

        foreach ($tables as $table) {
            RestaurantTable::updateOrCreate(
                ['table_number' => $table['table_number']],
                [
                    'capacity' => $table['capacity'],
                    'location' => $table['location'],
                    'status' => 'available',
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('12 restaurant tables seeded successfully.');
    }
}