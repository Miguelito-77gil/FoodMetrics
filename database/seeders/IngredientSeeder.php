<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            // Meat and poultry
            ['name' => 'Chicken Breast', 'base_unit' => 'kg', 'current_stock' => 25.000, 'minimum_stock' => 5.000, 'cost_per_base_unit' => 220.0000, 'storage_location' => 'Freezer A'],
            ['name' => 'Chicken Thigh', 'base_unit' => 'kg', 'current_stock' => 20.000, 'minimum_stock' => 5.000, 'cost_per_base_unit' => 195.0000, 'storage_location' => 'Freezer A'],
            ['name' => 'Pork Belly', 'base_unit' => 'kg', 'current_stock' => 18.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 340.0000, 'storage_location' => 'Freezer A'],
            ['name' => 'Ground Beef', 'base_unit' => 'kg', 'current_stock' => 12.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 390.0000, 'storage_location' => 'Freezer B'],
            ['name' => 'Beef Sirloin', 'base_unit' => 'kg', 'current_stock' => 15.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 480.0000, 'storage_location' => 'Freezer B'],

            // Seafood
            ['name' => 'Shrimp', 'base_unit' => 'kg', 'current_stock' => 10.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 420.0000, 'storage_location' => 'Freezer B'],
            ['name' => 'Squid', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 350.0000, 'storage_location' => 'Freezer B'],
            ['name' => 'Fish Fillet', 'base_unit' => 'kg', 'current_stock' => 12.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 280.0000, 'storage_location' => 'Freezer B'],

            // Vegetables
            ['name' => 'Potatoes', 'base_unit' => 'kg', 'current_stock' => 30.000, 'minimum_stock' => 8.000, 'cost_per_base_unit' => 95.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Carrots', 'base_unit' => 'kg', 'current_stock' => 15.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 85.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Onions', 'base_unit' => 'kg', 'current_stock' => 20.000, 'minimum_stock' => 5.000, 'cost_per_base_unit' => 110.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Garlic', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 180.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Tomatoes', 'base_unit' => 'kg', 'current_stock' => 15.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 100.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Lettuce', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 160.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Bell Peppers', 'base_unit' => 'kg', 'current_stock' => 10.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 190.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Cabbage', 'base_unit' => 'kg', 'current_stock' => 12.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 75.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Broccoli', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 210.0000, 'storage_location' => 'Cold Storage'],

            // Fruits
            ['name' => 'Lemon', 'base_unit' => 'kg', 'current_stock' => 6.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 180.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Avocado', 'base_unit' => 'kg', 'current_stock' => 5.000, 'minimum_stock' => 1.500, 'cost_per_base_unit' => 220.0000, 'storage_location' => 'Cold Storage'],

            // Dairy and eggs
            ['name' => 'Fresh Milk', 'base_unit' => 'L', 'current_stock' => 20.000, 'minimum_stock' => 5.000, 'cost_per_base_unit' => 95.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'All-Purpose Cream', 'base_unit' => 'L', 'current_stock' => 15.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 145.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Cheddar Cheese', 'base_unit' => 'kg', 'current_stock' => 10.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 420.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Butter', 'base_unit' => 'kg', 'current_stock' => 8.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 380.0000, 'storage_location' => 'Cold Storage'],
            ['name' => 'Eggs', 'base_unit' => 'pcs', 'current_stock' => 180.000, 'minimum_stock' => 36.000, 'cost_per_base_unit' => 9.0000, 'storage_location' => 'Cold Storage'],

            // Dry goods and seasonings
            ['name' => 'White Rice', 'base_unit' => 'kg', 'current_stock' => 50.000, 'minimum_stock' => 12.000, 'cost_per_base_unit' => 58.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Spaghetti Pasta', 'base_unit' => 'kg', 'current_stock' => 20.000, 'minimum_stock' => 5.000, 'cost_per_base_unit' => 95.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'All-Purpose Flour', 'base_unit' => 'kg', 'current_stock' => 15.000, 'minimum_stock' => 4.000, 'cost_per_base_unit' => 65.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Cooking Oil', 'base_unit' => 'L', 'current_stock' => 25.000, 'minimum_stock' => 6.000, 'cost_per_base_unit' => 95.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Soy Sauce', 'base_unit' => 'L', 'current_stock' => 12.000, 'minimum_stock' => 3.000, 'cost_per_base_unit' => 65.0000, 'storage_location' => 'Dry Storage'],
            ['name' => 'Salt', 'base_unit' => 'kg', 'current_stock' => 10.000, 'minimum_stock' => 2.000, 'cost_per_base_unit' => 25.0000, 'storage_location' => 'Dry Storage'],
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::updateOrCreate(
                ['name' => $ingredient['name']],
                [
                    'base_unit' => $ingredient['base_unit'],
                    'current_stock' => $ingredient['current_stock'],
                    'minimum_stock' => $ingredient['minimum_stock'],
                    'cost_per_base_unit' => $ingredient['cost_per_base_unit'],
                    'storage_location' => $ingredient['storage_location'],
                    'notes' => 'Synthetic sample inventory record for FoodMetrics analytics.',
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('30 simulated FoodMetrics ingredients seeded successfully.');
    }
}