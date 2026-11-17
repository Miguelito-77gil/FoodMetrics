<?php

namespace Database\Seeders;

use App\Models\AddOn;
use Illuminate\Database\Seeder;

class AddOnSeeder extends Seeder
{
    public function run(): void
    {
        $addOns = [
            [
                'name' => 'Extra Cheese',
                'description' => 'Additional melted cheese topping.',
                'price' => 35.00,
                'sort_order' => 1,
            ],
            [
                'name' => 'Bacon',
                'description' => 'Crispy bacon pieces.',
                'price' => 55.00,
                'sort_order' => 2,
            ],
            [
                'name' => 'Extra Rice',
                'description' => 'One additional serving of steamed rice.',
                'price' => 25.00,
                'sort_order' => 3,
            ],
            [
                'name' => 'Fried Egg',
                'description' => 'Freshly cooked fried egg.',
                'price' => 20.00,
                'sort_order' => 4,
            ],
            [
                'name' => 'Garlic Rice',
                'description' => 'One serving of garlic fried rice.',
                'price' => 35.00,
                'sort_order' => 5,
            ],
            [
                'name' => 'French Fries',
                'description' => 'Crispy golden potato fries.',
                'price' => 65.00,
                'sort_order' => 6,
            ],
            [
                'name' => 'Grilled Chicken',
                'description' => 'Additional grilled chicken serving.',
                'price' => 85.00,
                'sort_order' => 7,
            ],
            [
                'name' => 'Extra Sauce',
                'description' => 'Additional serving of house sauce.',
                'price' => 15.00,
                'sort_order' => 8,
            ],
            [
                'name' => 'Mushrooms',
                'description' => 'Sauteed mushroom topping.',
                'price' => 40.00,
                'sort_order' => 9,
            ],
            [
                'name' => 'Vegetables',
                'description' => 'Additional fresh vegetable serving.',
                'price' => 30.00,
                'sort_order' => 10,
            ],
            [
                'name' => 'Shrimp',
                'description' => 'Additional shrimp topping.',
                'price' => 75.00,
                'sort_order' => 11,
            ],
            [
                'name' => 'Avocado',
                'description' => 'Fresh avocado topping.',
                'price' => 50.00,
                'sort_order' => 12,
            ],
        ];

        foreach ($addOns as $addOn) {
            AddOn::updateOrCreate(
                ['name' => $addOn['name']],
                [
                    'description' => $addOn['description'],
                    'price' => $addOn['price'],
                    'is_available' => true,
                    'sort_order' => $addOn['sort_order'],
                ]
            );
        }

        $this->command?->info('12 FoodMetrics add-ons seeded successfully.');
    }
}