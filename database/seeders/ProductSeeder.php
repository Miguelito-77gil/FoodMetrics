<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'appetizers' => [
                ['name' => 'Crispy Calamari', 'price' => 245, 'time' => 15, 'featured' => true],
                ['name' => 'Garlic Butter Shrimp Bites', 'price' => 285, 'time' => 18, 'featured' => true],
                ['name' => 'Chicken Spring Rolls', 'price' => 165, 'time' => 12, 'featured' => false],
                ['name' => 'Cheese Sticks', 'price' => 145, 'time' => 10, 'featured' => false],
                ['name' => 'Loaded Potato Wedges', 'price' => 185, 'time' => 15, 'featured' => false],
            ],

            'soups' => [
                ['name' => 'Cream of Mushroom Soup', 'price' => 125, 'time' => 12, 'featured' => false],
                ['name' => 'Roasted Pumpkin Soup', 'price' => 135, 'time' => 15, 'featured' => false],
                ['name' => 'Chicken and Corn Soup', 'price' => 145, 'time' => 15, 'featured' => false],
                ['name' => 'Seafood Chowder', 'price' => 195, 'time' => 20, 'featured' => true],
            ],

            'salads' => [
                ['name' => 'Classic Garden Salad', 'price' => 165, 'time' => 10, 'featured' => false],
                ['name' => 'Caesar Salad', 'price' => 195, 'time' => 12, 'featured' => true],
                ['name' => 'Grilled Chicken Salad', 'price' => 245, 'time' => 18, 'featured' => true],
                ['name' => 'Avocado Tomato Salad', 'price' => 215, 'time' => 12, 'featured' => false],
            ],

            'main-courses' => [
                ['name' => 'Grilled Chicken with Herb Butter', 'price' => 325, 'time' => 25, 'featured' => true],
                ['name' => 'Beef Steak with Mushroom Sauce', 'price' => 425, 'time' => 30, 'featured' => true],
                ['name' => 'Roasted Pork Belly', 'price' => 365, 'time' => 30, 'featured' => true],
                ['name' => 'Crispy Fried Chicken', 'price' => 285, 'time' => 25, 'featured' => false],
                ['name' => 'Grilled Pork Chop', 'price' => 315, 'time' => 25, 'featured' => false],
                ['name' => 'Beef Stroganoff', 'price' => 395, 'time' => 30, 'featured' => false],
                ['name' => 'Chicken Parmesan', 'price' => 345, 'time' => 28, 'featured' => false],
                ['name' => 'Herb Roasted Chicken', 'price' => 335, 'time' => 35, 'featured' => false],
            ],

            'pasta' => [
                ['name' => 'Classic Spaghetti Bolognese', 'price' => 245, 'time' => 20, 'featured' => true],
                ['name' => 'Creamy Carbonara', 'price' => 265, 'time' => 20, 'featured' => true],
                ['name' => 'Garlic Shrimp Pasta', 'price' => 325, 'time' => 22, 'featured' => true],
                ['name' => 'Creamy Mushroom Pasta', 'price' => 245, 'time' => 18, 'featured' => false],
                ['name' => 'Chicken Alfredo', 'price' => 285, 'time' => 22, 'featured' => false],
            ],

            'rice-meals' => [
                ['name' => 'Chicken Teriyaki Rice Bowl', 'price' => 225, 'time' => 18, 'featured' => true],
                ['name' => 'Beef Pepper Rice Bowl', 'price' => 275, 'time' => 20, 'featured' => true],
                ['name' => 'Pork Sisig Rice Meal', 'price' => 245, 'time' => 20, 'featured' => true],
                ['name' => 'Garlic Chicken Rice Meal', 'price' => 215, 'time' => 18, 'featured' => false],
                ['name' => 'Grilled Fish Rice Meal', 'price' => 255, 'time' => 22, 'featured' => false],
                ['name' => 'Crispy Pork Rice Bowl', 'price' => 265, 'time' => 22, 'featured' => false],
            ],

            'seafood' => [
                ['name' => 'Grilled Garlic Butter Shrimp', 'price' => 385, 'time' => 22, 'featured' => true],
                ['name' => 'Crispy Fish Fillet', 'price' => 295, 'time' => 20, 'featured' => false],
                ['name' => 'Sweet and Sour Fish', 'price' => 315, 'time' => 25, 'featured' => false],
                ['name' => 'Buttered Squid', 'price' => 345, 'time' => 22, 'featured' => true],
                ['name' => 'Seafood Mixed Platter', 'price' => 495, 'time' => 30, 'featured' => true],
            ],

            'desserts' => [
                ['name' => 'Classic Cheesecake', 'price' => 165, 'time' => 5, 'featured' => true],
                ['name' => 'Chocolate Lava Cake', 'price' => 185, 'time' => 12, 'featured' => true],
                ['name' => 'Mango Panna Cotta', 'price' => 145, 'time' => 5, 'featured' => false],
                ['name' => 'Caramel Custard Flan', 'price' => 125, 'time' => 5, 'featured' => false],
                ['name' => 'Brownie with Ice Cream', 'price' => 175, 'time' => 8, 'featured' => false],
            ],

            'beverages' => [
                ['name' => 'Fresh Lemonade', 'price' => 85, 'time' => 5, 'featured' => true],
                ['name' => 'Iced Tea', 'price' => 65, 'time' => 3, 'featured' => false],
                ['name' => 'Fresh Mango Shake', 'price' => 125, 'time' => 7, 'featured' => true],
                ['name' => 'Iced Chocolate', 'price' => 115, 'time' => 5, 'featured' => false],
                ['name' => 'Brewed Coffee', 'price' => 95, 'time' => 5, 'featured' => false],
            ],

            'snacks-and-sides' => [
                ['name' => 'Steamed White Rice', 'price' => 35, 'time' => 5, 'featured' => false],
                ['name' => 'Garlic Fried Rice', 'price' => 55, 'time' => 8, 'featured' => true],
                ['name' => 'French Fries', 'price' => 95, 'time' => 12, 'featured' => false],
            ],
        ];

        $descriptions = [
            'appetizers' => 'A freshly prepared starter from the Olive Grove Kitchen menu.',
            'soups' => 'A comforting soup prepared with carefully selected ingredients.',
            'salads' => 'A fresh salad made with quality vegetables and house dressing.',
            'main-courses' => 'A satisfying main course prepared by our kitchen team.',
            'pasta' => 'A freshly cooked pasta dish with a flavorful house-made sauce.',
            'rice-meals' => 'A complete Filipino-inspired rice meal prepared fresh to order.',
            'seafood' => 'A seafood specialty prepared with quality ingredients.',
            'desserts' => 'A sweet dessert prepared for a satisfying finish.',
            'beverages' => 'A refreshing beverage prepared fresh for our guests.',
            'snacks-and-sides' => 'A freshly prepared side dish or snack.',
        ];

        $categoryIds = Category::whereIn('slug', array_keys($catalog))
            ->pluck('id', 'slug');

        foreach (array_keys($catalog) as $slug) {
            if (!$categoryIds->has($slug)) {
                throw new RuntimeException(
                    "Required category is missing or inactive: {$slug}"
                );
            }
        }

        $counter = 0;

        foreach ($catalog as $categorySlug => $products) {
            foreach ($products as $item) {
                $counter++;

                $productName = $item['name'];

                Product::updateOrCreate(
                    ['product_code' => sprintf('PRD-%04d', $counter)],
                    [
                        'category_id' => $categoryIds[$categorySlug],
                        'name' => $productName,
                        'slug' => Str::slug($productName),
                        'description' => $descriptions[$categorySlug],
                        'image' => null,
                        'base_price' => $item['price'],
                        'is_available' => true,
                        'is_featured' => $item['featured'],
                        'preparation_time' => $item['time'],
                    ]
                );
            }
        }

        $this->command?->info("{$counter} simulated FoodMetrics products seeded successfully.");
    }
}