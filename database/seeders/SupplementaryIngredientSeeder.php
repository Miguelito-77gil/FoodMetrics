<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplementaryIngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            ['name' => 'Mushrooms', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 280.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Pumpkin', 'base_unit' => 'kg', 'current_stock' => 10.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 85.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Sweet Corn', 'base_unit' => 'kg', 'current_stock' => 6.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 120.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Cucumber', 'base_unit' => 'kg', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 90.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Cream Cheese', 'base_unit' => 'kg', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 420.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Parmesan Cheese', 'base_unit' => 'kg', 'current_stock' => 4.000, 'minimum_stock' => 1.000, 'cost_per_base_unit' => 650.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Condensed Milk', 'base_unit' => 'L', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 110.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Sugar', 'base_unit' => 'kg', 'current_stock' => 12.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 85.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Cocoa Powder', 'base_unit' => 'kg', 'current_stock' => 3.000, 'minimum_stock' => 1.000, 'cost_per_base_unit' => 520.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Dark Chocolate', 'base_unit' => 'kg', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 680.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Mango', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 150.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Banana', 'base_unit' => 'kg', 'current_stock' => 6.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 85.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Black Pepper', 'base_unit' => 'kg', 'current_stock' => 2.000, 'minimum_stock' => 0.500, 'cost_per_base_unit' => 480.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Paprika', 'base_unit' => 'kg', 'current_stock' => 2.000, 'minimum_stock' => 0.500, 'cost_per_base_unit' => 390.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Dried Oregano', 'base_unit' => 'kg', 'current_stock' => 1.500, 'minimum_stock' => 0.400, 'cost_per_base_unit' => 550.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Parsley', 'base_unit' => 'kg', 'current_stock' => 2.000, 'minimum_stock' => 0.500, 'cost_per_base_unit' => 260.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Breadcrumbs', 'base_unit' => 'kg', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 95.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Mayonnaise', 'base_unit' => 'L', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 180.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Vanilla Extract', 'base_unit' => 'L', 'current_stock' => 1.500, 'minimum_stock' => 0.400, 'cost_per_base_unit' => 950.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Gelatin', 'base_unit' => 'kg', 'current_stock' => 2.000, 'minimum_stock' => 0.500, 'cost_per_base_unit' => 420.0000, 'storage_location' => 'Dry Storage'],
        ];

        DB::transaction(function () use ($ingredients) {
            foreach ($ingredients as $ingredient) {
                Ingredient::updateOrCreate(
                    ['name' => $ingredient['name']],
                    [
                        'base_unit' => $ingredient['base_unit'],
                        'current_stock' => $ingredient['current_stock'],
                        'minimum_stock' => $ingredient['minimum_stock'],
                        'cost_per_base_unit' => $ingredient['cost_per_base_unit'],
                        'storage_location' => $ingredient['storage_location'],
                        'notes' => 'Synthetic supplementary inventory record for FoodMetrics analytics.',
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->command?->info('20 supplementary ingredients seeded successfully.');
    }
}