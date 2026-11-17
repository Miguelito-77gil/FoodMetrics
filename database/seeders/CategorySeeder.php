<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Appetizers',
                'slug' => 'appetizers',
                'description' => 'Starters and small dishes served before the main course.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Soups',
                'slug' => 'soups',
                'description' => 'Warm and flavorful soup selections.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Salads',
                'slug' => 'salads',
                'description' => 'Fresh vegetable and specialty salad dishes.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Main Courses',
                'slug' => 'main-courses',
                'description' => 'Signature meals and full-sized restaurant entrees.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Pasta',
                'slug' => 'pasta',
                'description' => 'Italian-inspired pasta dishes and specialties.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Rice Meals',
                'slug' => 'rice-meals',
                'description' => 'Complete meals served with rice.',
                'sort_order' => 6,
            ],
            [
                'name' => 'Seafood',
                'slug' => 'seafood',
                'description' => 'Fish, shellfish, and other seafood specialties.',
                'sort_order' => 7,
            ],
            [
                'name' => 'Desserts',
                'slug' => 'desserts',
                'description' => 'Sweet treats and desserts served after meals.',
                'sort_order' => 8,
            ],
            [
                'name' => 'Beverages',
                'slug' => 'beverages',
                'description' => 'Hot, cold, and refreshing drinks.',
                'sort_order' => 9,
            ],
            [
                'name' => 'Snacks and Sides',
                'slug' => 'snacks-and-sides',
                'description' => 'Light snacks, side dishes, and meal complements.',
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }

        $this->command?->info('10 FoodMetrics categories seeded successfully.');
    }
}