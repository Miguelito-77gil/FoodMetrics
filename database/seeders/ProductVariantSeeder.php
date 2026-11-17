<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $variantData = [
            'PRD-0001' => [
                ['name' => 'Regular Serving', 'adjustment' => 0, 'sku' => 'VAR-0001'],
                ['name' => 'Large Serving', 'adjustment' => 85, 'sku' => 'VAR-0002'],
            ],

            'PRD-0002' => [
                ['name' => 'Regular Serving', 'adjustment' => 0, 'sku' => 'VAR-0003'],
                ['name' => 'Large Serving', 'adjustment' => 95, 'sku' => 'VAR-0004'],
            ],

            'PRD-0015' => [
                ['name' => 'Regular Portion', 'adjustment' => 0, 'sku' => 'VAR-0005'],
                ['name' => 'Large Portion', 'adjustment' => 120, 'sku' => 'VAR-0006'],
            ],

            'PRD-0016' => [
                ['name' => 'Regular Portion', 'adjustment' => 0, 'sku' => 'VAR-0007'],
                ['name' => 'Large Portion', 'adjustment' => 150, 'sku' => 'VAR-0008'],
            ],

            'PRD-0021' => [
                ['name' => 'Regular Pasta', 'adjustment' => 0, 'sku' => 'VAR-0009'],
                ['name' => 'Large Pasta', 'adjustment' => 85, 'sku' => 'VAR-0010'],
            ],

            'PRD-0022' => [
                ['name' => 'Regular Pasta', 'adjustment' => 0, 'sku' => 'VAR-0011'],
                ['name' => 'Large Pasta', 'adjustment' => 90, 'sku' => 'VAR-0012'],
            ],

            'PRD-0026' => [
                ['name' => 'Regular Bowl', 'adjustment' => 0, 'sku' => 'VAR-0013'],
                ['name' => 'Large Bowl', 'adjustment' => 75, 'sku' => 'VAR-0014'],
            ],

            'PRD-0027' => [
                ['name' => 'Regular Bowl', 'adjustment' => 0, 'sku' => 'VAR-0015'],
                ['name' => 'Large Bowl', 'adjustment' => 95, 'sku' => 'VAR-0016'],
            ],

            'PRD-0031' => [
                ['name' => 'Regular Portion', 'adjustment' => 0, 'sku' => 'VAR-0017'],
                ['name' => 'Large Portion', 'adjustment' => 130, 'sku' => 'VAR-0018'],
            ],

            'PRD-0035' => [
                ['name' => 'Regular Platter', 'adjustment' => 0, 'sku' => 'VAR-0019'],
                ['name' => 'Family Platter', 'adjustment' => 250, 'sku' => 'VAR-0020'],
            ],

            'PRD-0036' => [
                ['name' => 'Single Slice', 'adjustment' => 0, 'sku' => 'VAR-0021'],
                ['name' => 'Whole Cake', 'adjustment' => 650, 'sku' => 'VAR-0022'],
            ],

            'PRD-0041' => [
                ['name' => 'Small', 'adjustment' => 0, 'sku' => 'VAR-0023'],
                ['name' => 'Medium', 'adjustment' => 25, 'sku' => 'VAR-0024'],
                ['name' => 'Large', 'adjustment' => 50, 'sku' => 'VAR-0025'],
            ],
        ];

        $productCodes = array_keys($variantData);

        $products = Product::whereIn('product_code', $productCodes)
            ->get()
            ->keyBy('product_code');

        foreach ($productCodes as $code) {
            if (!$products->has($code)) {
                throw new RuntimeException(
                    "Required product does not exist: {$code}"
                );
            }
        }

        $createdCount = 0;

        foreach ($variantData as $productCode => $variants) {
            $product = $products[$productCode];

            foreach ($variants as $variant) {
                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'name' => $variant['name'],
                    ],
                    [
                        'sku' => $variant['sku'],
                        'price_adjustment' => $variant['adjustment'],
                        'is_available' => true,
                        'sort_order' => $createdCount + 1,
                    ]
                );

                $createdCount++;
            }
        }

        $this->command?->info(
            "{$createdCount} simulated product variants seeded successfully."
        );
    }
}