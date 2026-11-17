<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductAddOnSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            'PRD-0001' => ['Extra Sauce'],
            'PRD-0002' => ['Extra Sauce', 'Shrimp'],
            'PRD-0003' => ['Extra Sauce'],
            'PRD-0004' => ['Extra Cheese'],
            'PRD-0005' => ['Bacon', 'Extra Cheese'],

            'PRD-0006' => ['Mushrooms', 'Extra Cheese'],
            'PRD-0007' => ['Vegetables'],
            'PRD-0008' => ['Vegetables', 'Grilled Chicken'],
            'PRD-0009' => ['Shrimp', 'Extra Sauce'],

            'PRD-0010' => ['Avocado', 'Grilled Chicken'],
            'PRD-0011' => ['Grilled Chicken', 'Extra Cheese'],
            'PRD-0012' => ['Avocado', 'Vegetables'],
            'PRD-0013' => ['Grilled Chicken'],

            'PRD-0014' => ['Extra Sauce', 'Vegetables'],
            'PRD-0015' => ['Mushrooms', 'Extra Sauce'],
            'PRD-0016' => ['Extra Sauce', 'Vegetables'],
            'PRD-0017' => ['Extra Rice', 'Extra Sauce'],
            'PRD-0018' => ['Extra Rice', 'Mushrooms'],
            'PRD-0019' => ['Mushrooms', 'Extra Cheese'],
            'PRD-0020' => ['Extra Cheese', 'Mushrooms'],
            'PRD-0021' => ['Extra Sauce', 'Vegetables'],

            'PRD-0022' => ['Extra Cheese', 'Mushrooms'],
            'PRD-0023' => ['Extra Cheese', 'Bacon'],
            'PRD-0024' => ['Shrimp', 'Extra Cheese'],
            'PRD-0025' => ['Mushrooms', 'Extra Cheese'],
            'PRD-0026' => ['Extra Cheese', 'Bacon'],

            'PRD-0027' => ['Extra Rice', 'Fried Egg', 'Garlic Rice'],
            'PRD-0028' => ['Extra Rice', 'Fried Egg'],
            'PRD-0029' => ['Extra Rice', 'Fried Egg'],
            'PRD-0030' => ['Extra Rice', 'Garlic Rice'],
            'PRD-0031' => ['Extra Rice', 'Vegetables'],
            'PRD-0032' => ['Extra Rice', 'Fried Egg'],

            'PRD-0033' => ['Extra Sauce', 'Shrimp'],
            'PRD-0034' => ['Extra Sauce'],
            'PRD-0035' => ['Extra Sauce', 'Vegetables'],
            'PRD-0036' => ['Extra Sauce'],
            'PRD-0037' => ['Shrimp', 'Extra Sauce'],
        ];

        $addonNames = collect($assignments)
            ->flatten()
            ->unique()
            ->values();

        $addons = AddOn::whereIn('name', $addonNames)
            ->where('is_available', true)
            ->get()
            ->keyBy('name');

        foreach ($addonNames as $name) {
            if (!$addons->has($name)) {
                throw new RuntimeException(
                    "Required available add-on is missing: {$name}"
                );
            }
        }

        $productCodes = array_keys($assignments);

        $products = Product::whereIn('product_code', $productCodes)
            ->get()
            ->keyBy('product_code');

        foreach ($productCodes as $code) {
            if (!$products->has($code)) {
                throw new RuntimeException(
                    "Required product is missing: {$code}"
                );
            }
        }

        DB::transaction(function () use ($assignments, $products, $addons) {
            foreach ($assignments as $productCode => $addonList) {
                $product = $products[$productCode];

                foreach ($addonList as $addonName) {
                    $addon = $addons[$addonName];

                    DB::table('product_add_ons')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'add_on_id' => $addon->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });

        $totalLinks = collect($assignments)
            ->sum(fn ($items) => count($items));

        $this->command?->info(
            "{$totalLinks} product-add-on relationships seeded successfully."
        );
    }
}