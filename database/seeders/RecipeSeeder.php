<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Quantities are measured per standard serving.
         * All quantities use the ingredient's base unit.
         */
        $baseRecipes = [
            'PRD-0001' => ['Squid'=>0.180, 'All-Purpose Flour'=>0.035, 'Breadcrumbs'=>0.025, 'Eggs'=>1, 'Cooking Oil'=>0.025, 'Salt'=>0.002, 'Black Pepper'=>0.001],
            'PRD-0002' => ['Shrimp'=>0.180, 'Butter'=>0.025, 'Garlic'=>0.008, 'Lemon'=>0.015, 'Parsley'=>0.003, 'Salt'=>0.002],
            'PRD-0003' => ['Chicken Breast'=>0.100, 'Cabbage'=>0.035, 'Carrots'=>0.020, 'Onions'=>0.015, 'All-Purpose Flour'=>0.025, 'Cooking Oil'=>0.020, 'Soy Sauce'=>0.010],
            'PRD-0004' => ['Cheddar Cheese'=>0.080, 'All-Purpose Flour'=>0.025, 'Breadcrumbs'=>0.025, 'Eggs'=>1, 'Cooking Oil'=>0.020],
            'PRD-0005' => ['Potatoes'=>0.250, 'Cheddar Cheese'=>0.035, 'Pork Belly'=>0.035],
            'PRD-0006' => ['Mushrooms'=>0.100, 'All-Purpose Cream'=>0.060, 'Fresh Milk'=>0.080, 'Butter'=>0.015, 'Onions'=>0.020, 'All-Purpose Flour'=>0.010, 'Salt'=>0.002],
            'PRD-0007' => ['Pumpkin'=>0.180, 'Fresh Milk'=>0.080, 'All-Purpose Cream'=>0.050, 'Onions'=>0.020, 'Butter'=>0.012, 'Black Pepper'=>0.001, 'Salt'=>0.002],
            'PRD-0008' => ['Chicken Breast'=>0.070, 'Sweet Corn'=>0.060, 'Carrots'=>0.025, 'Onions'=>0.015, 'Fresh Milk'=>0.050, 'Eggs'=>1, 'Salt'=>0.002],
            'PRD-0009' => ['Shrimp'=>0.050, 'Squid'=>0.040, 'Fish Fillet'=>0.050, 'Potatoes'=>0.050, 'Fresh Milk'=>0.080, 'All-Purpose Cream'=>0.040, 'Carrots'=>0.020, 'Butter'=>0.010],
            'PRD-0010' => ['Lettuce'=>0.080, 'Tomatoes'=>0.040, 'Cucumber'=>0.035, 'Carrots'=>0.020, 'Mayonnaise'=>0.025],
            'PRD-0011' => ['Lettuce'=>0.090, 'Chicken Breast'=>0.070, 'Parmesan Cheese'=>0.012, 'Mayonnaise'=>0.025, 'Lemon'=>0.008, 'Breadcrumbs'=>0.010],
            'PRD-0012' => ['Chicken Breast'=>0.120, 'Lettuce'=>0.070, 'Tomatoes'=>0.035, 'Cucumber'=>0.025, 'Mayonnaise'=>0.020, 'Lemon'=>0.008],
            'PRD-0013' => ['Avocado'=>0.080, 'Tomatoes'=>0.070, 'Cucumber'=>0.030, 'Lettuce'=>0.050, 'Lemon'=>0.010, 'Salt'=>0.001],
            'PRD-0014' => ['Chicken Breast'=>0.200, 'Butter'=>0.025, 'Garlic'=>0.008, 'Dried Oregano'=>0.002, 'Parsley'=>0.003, 'Potatoes'=>0.080, 'Salt'=>0.002, 'Black Pepper'=>0.001],
            'PRD-0015' => ['Beef Sirloin'=>0.200, 'Mushrooms'=>0.060, 'All-Purpose Cream'=>0.050, 'Onions'=>0.020, 'Butter'=>0.015, 'Garlic'=>0.005, 'Black Pepper'=>0.002, 'Salt'=>0.002],
            'PRD-0016' => ['Pork Belly'=>0.220, 'Garlic'=>0.010, 'Soy Sauce'=>0.015, 'Black Pepper'=>0.002, 'Salt'=>0.002, 'Cooking Oil'=>0.010],
            'PRD-0017' => ['Chicken Thigh'=>0.220, 'All-Purpose Flour'=>0.035, 'Breadcrumbs'=>0.025, 'Eggs'=>1, 'Paprika'=>0.002, 'Cooking Oil'=>0.030, 'Salt'=>0.003],
            'PRD-0018' => ['Pork Belly'=>0.200, 'Soy Sauce'=>0.020, 'Garlic'=>0.008, 'Lemon'=>0.010, 'Black Pepper'=>0.002, 'Cooking Oil'=>0.010],
            'PRD-0019' => ['Beef Sirloin'=>0.150, 'Mushrooms'=>0.050, 'All-Purpose Cream'=>0.060, 'Onions'=>0.025, 'Butter'=>0.012, 'All-Purpose Flour'=>0.010, 'Black Pepper'=>0.002],
            'PRD-0020' => ['Chicken Breast'=>0.160, 'Tomatoes'=>0.060, 'Cheddar Cheese'=>0.035, 'Parmesan Cheese'=>0.010, 'Breadcrumbs'=>0.025, 'Eggs'=>1, 'All-Purpose Flour'=>0.020, 'Cooking Oil'=>0.015],
            'PRD-0021' => ['Chicken Thigh'=>0.220, 'Butter'=>0.020, 'Garlic'=>0.008, 'Dried Oregano'=>0.003, 'Parsley'=>0.003, 'Lemon'=>0.010, 'Salt'=>0.002, 'Black Pepper'=>0.001],
            'PRD-0022' => ['Spaghetti Pasta'=>0.120, 'Ground Beef'=>0.090, 'Tomatoes'=>0.080, 'Onions'=>0.020, 'Garlic'=>0.006, 'Cooking Oil'=>0.010, 'Sugar'=>0.003, 'Salt'=>0.002],
            'PRD-0023' => ['Spaghetti Pasta'=>0.120, 'Chicken Thigh'=>0.050, 'Eggs'=>1, 'All-Purpose Cream'=>0.060, 'Parmesan Cheese'=>0.015, 'Garlic'=>0.005, 'Black Pepper'=>0.001],
            'PRD-0024' => ['Spaghetti Pasta'=>0.120, 'Shrimp'=>0.100, 'Butter'=>0.020, 'Garlic'=>0.010, 'Lemon'=>0.010, 'Parsley'=>0.003, 'Parmesan Cheese'=>0.008],
            'PRD-0025' => ['Spaghetti Pasta'=>0.120, 'Mushrooms'=>0.080, 'All-Purpose Cream'=>0.070, 'Butter'=>0.015, 'Garlic'=>0.006, 'Parmesan Cheese'=>0.012, 'Black Pepper'=>0.001],
            'PRD-0026' => ['Spaghetti Pasta'=>0.120, 'Chicken Breast'=>0.100, 'All-Purpose Cream'=>0.070, 'Parmesan Cheese'=>0.015, 'Butter'=>0.012, 'Garlic'=>0.005, 'Black Pepper'=>0.001],
            'PRD-0027' => ['Chicken Thigh'=>0.120, 'White Rice'=>0.150, 'Soy Sauce'=>0.020, 'Sugar'=>0.005, 'Garlic'=>0.006, 'Broccoli'=>0.030, 'Carrots'=>0.020],
            'PRD-0028' => ['Beef Sirloin'=>0.120, 'White Rice'=>0.150, 'Bell Peppers'=>0.030, 'Onions'=>0.020, 'Soy Sauce'=>0.015, 'Butter'=>0.010, 'Black Pepper'=>0.002],
            'PRD-0029' => ['Pork Belly'=>0.130, 'White Rice'=>0.150, 'Onions'=>0.025, 'Mayonnaise'=>0.015, 'Soy Sauce'=>0.015, 'Lemon'=>0.008, 'Black Pepper'=>0.002],
            'PRD-0030' => ['Chicken Breast'=>0.120, 'White Rice'=>0.150, 'Garlic'=>0.012, 'Soy Sauce'=>0.015, 'Butter'=>0.010, 'Black Pepper'=>0.001],
            'PRD-0031' => ['Fish Fillet'=>0.160, 'White Rice'=>0.150, 'Lemon'=>0.012, 'Butter'=>0.012, 'Garlic'=>0.006, 'Broccoli'=>0.025, 'Salt'=>0.002],
            'PRD-0032' => ['Pork Belly'=>0.140, 'White Rice'=>0.150, 'All-Purpose Flour'=>0.025, 'Breadcrumbs'=>0.020, 'Eggs'=>1, 'Cooking Oil'=>0.025, 'Salt'=>0.002],
            'PRD-0033' => ['Shrimp'=>0.200, 'Butter'=>0.025, 'Garlic'=>0.010, 'Lemon'=>0.012, 'Parsley'=>0.003, 'Salt'=>0.002],
            'PRD-0034' => ['Fish Fillet'=>0.180, 'All-Purpose Flour'=>0.035, 'Breadcrumbs'=>0.030, 'Eggs'=>1, 'Cooking Oil'=>0.030, 'Paprika'=>0.002, 'Salt'=>0.002],
            'PRD-0035' => ['Fish Fillet'=>0.180, 'Bell Peppers'=>0.035, 'Tomatoes'=>0.040, 'Onions'=>0.025, 'Sugar'=>0.008, 'Soy Sauce'=>0.012, 'All-Purpose Flour'=>0.020, 'Cooking Oil'=>0.020],
            'PRD-0036' => ['Squid'=>0.180, 'Butter'=>0.025, 'Garlic'=>0.010, 'Lemon'=>0.010, 'Parsley'=>0.003, 'Salt'=>0.002],
            'PRD-0037' => ['Shrimp'=>0.120, 'Squid'=>0.100, 'Fish Fillet'=>0.120, 'Butter'=>0.025, 'Garlic'=>0.010, 'Lemon'=>0.015, 'Parsley'=>0.004],
            'PRD-0038' => ['Cream Cheese'=>0.100, 'All-Purpose Cream'=>0.050, 'Sugar'=>0.035, 'Eggs'=>1, 'All-Purpose Flour'=>0.025, 'Butter'=>0.015, 'Vanilla Extract'=>0.003],
            'PRD-0039' => ['Dark Chocolate'=>0.060, 'Butter'=>0.035, 'Sugar'=>0.035, 'Eggs'=>1, 'All-Purpose Flour'=>0.025, 'Cocoa Powder'=>0.008, 'Vanilla Extract'=>0.002],
            'PRD-0040' => ['All-Purpose Cream'=>0.060, 'Fresh Milk'=>0.050, 'Mango'=>0.060, 'Sugar'=>0.025, 'Gelatin'=>0.004, 'Vanilla Extract'=>0.002],
            'PRD-0041' => ['Eggs'=>2, 'Fresh Milk'=>0.080, 'Condensed Milk'=>0.050, 'Sugar'=>0.025, 'Vanilla Extract'=>0.002],
            'PRD-0042' => ['All-Purpose Flour'=>0.045, 'Dark Chocolate'=>0.025, 'Cocoa Powder'=>0.010, 'Butter'=>0.025, 'Sugar'=>0.035, 'Eggs'=>1, 'Fresh Milk'=>0.020],
            'PRD-0043' => ['Lemon'=>0.080, 'Sugar'=>0.025, 'Fresh Milk'=>0.010],
            'PRD-0044' => ['Sugar'=>0.025, 'Lemon'=>0.008],
            'PRD-0045' => ['Mango'=>0.150, 'Fresh Milk'=>0.120, 'Condensed Milk'=>0.025, 'Sugar'=>0.010],
            'PRD-0046' => ['Fresh Milk'=>0.180, 'Cocoa Powder'=>0.015, 'Sugar'=>0.020, 'Condensed Milk'=>0.020],
            'PRD-0047' => ['Fresh Milk'=>0.030, 'Sugar'=>0.008],
            'PRD-0048' => ['White Rice'=>0.100, 'Salt'=>0.001],
            'PRD-0049' => ['White Rice'=>0.120, 'Garlic'=>0.008, 'Cooking Oil'=>0.010, 'Salt'=>0.002],
            'PRD-0050' => ['Potatoes'=>0.220, 'Cooking Oil'=>0.035, 'Salt'=>0.002, 'Paprika'=>0.001],
        ];

        $variantMultipliers = [
            'VAR-0001' => 1.00, 'VAR-0002' => 1.50,
            'VAR-0003' => 1.00, 'VAR-0004' => 1.50,
            'VAR-0005' => 1.00, 'VAR-0006' => 1.50,
            'VAR-0007' => 1.00, 'VAR-0008' => 1.50,
            'VAR-0009' => 1.00, 'VAR-0010' => 1.50,
            'VAR-0011' => 1.00, 'VAR-0012' => 1.50,
            'VAR-0013' => 1.00, 'VAR-0014' => 1.50,
            'VAR-0015' => 1.00, 'VAR-0016' => 1.50,
            'VAR-0017' => 1.00, 'VAR-0018' => 1.50,
            'VAR-0019' => 1.00, 'VAR-0020' => 2.50,
            'VAR-0021' => 1.00, 'VAR-0022' => 1.50,
            'VAR-0023' => 0.75, 'VAR-0024' => 1.00,
            'VAR-0025' => 1.50,
        ];

        $products = Product::query()->get()->keyBy('product_code');
        $variants = ProductVariant::query()->with('product')->get()->keyBy('sku');
        $ingredients = Ingredient::query()->where('is_active', true)->get()->keyBy('name');

        if ($products->count() !== 50 || count($baseRecipes) !== 50) {
            throw new RuntimeException('Expected exactly 50 products and 50 base recipe definitions.');
        }

        if ($variants->count() !== 25 || count($variantMultipliers) !== 25) {
            throw new RuntimeException('Expected exactly 25 variants and 25 variant multipliers.');
        }

        foreach ($baseRecipes as $productCode => $recipeIngredients) {
            if (!$products->has($productCode)) {
                throw new RuntimeException("Product not found: {$productCode}");
            }

            foreach ($recipeIngredients as $ingredientName => $quantity) {
                if (!$ingredients->has($ingredientName)) {
                    throw new RuntimeException("Ingredient not found: {$ingredientName} for {$productCode}");
                }

                if ($quantity <= 0) {
                    throw new RuntimeException("Invalid quantity for {$ingredientName} in {$productCode}");
                }
            }
        }

        DB::transaction(function () use ($baseRecipes, $variantMultipliers, $products, $variants, $ingredients) {
            $createdBase = 0;
            $createdVariants = 0;
            $ingredientLinks = 0;

            foreach ($baseRecipes as $productCode => $recipeIngredients) {
                $product = $products->get($productCode);

                $recipe = Recipe::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                    ],
                    [
                        'name' => $product->name . ' - Standard Recipe',
                        'instructions' => "Synthetic sample standard preparation procedure for {$product->name}. Measure all listed ingredients using their specified units. Prepare and cook using appropriate food-safe procedures, then portion and serve according to the restaurant's standard presentation.",
                        'is_active' => true,
                    ]
                );

                $recipe->recipeIngredients()->delete();

                foreach ($recipeIngredients as $ingredientName => $quantity) {
                    $ingredient = $ingredients->get($ingredientName);

                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredient->id,
                        'quantity_required' => $quantity,
                        'unit' => $ingredient->base_unit,
                        'preparation_notes' => "Standard measured quantity per base serving of {$product->name}.",
                    ]);

                    $ingredientLinks++;
                }

                $createdBase++;
            }

            foreach ($variantMultipliers as $sku => $multiplier) {
                $variant = $variants->get($sku);

                if (!$variant) {
                    throw new RuntimeException("Variant not found: {$sku}");
                }

                $productCode = $variant->product->product_code;
                $baseIngredients = $baseRecipes[$productCode] ?? null;

                if (!$baseIngredients) {
                    throw new RuntimeException("Base recipe definition missing for {$productCode}");
                }

                $recipe = Recipe::updateOrCreate(
                    [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'name' => $variant->product->name . ' - ' . $variant->name,
                        'instructions' => "Synthetic sample variant preparation procedure for {$variant->product->name} ({$variant->name}). Use the variant-specific ingredient quantities listed below. Measure, prepare, cook, and portion consistently according to the selected serving size.",
                        'is_active' => true,
                    ]
                );

                $recipe->recipeIngredients()->delete();

                foreach ($baseIngredients as $ingredientName => $baseQuantity) {
                    $ingredient = $ingredients->get($ingredientName);
                    $scaledQuantity = round($baseQuantity * $multiplier, 3);

                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredient->id,
                        'quantity_required' => $scaledQuantity,
                        'unit' => $ingredient->base_unit,
                        'preparation_notes' => "Variant quantity scaled by {$multiplier} for {$variant->name}.",
                    ]);

                    $ingredientLinks++;
                }

                $createdVariants++;
            }

            $this->command?->info("{$createdBase} base recipes created or updated.");
            $this->command?->info("{$createdVariants} variant-specific recipes created or updated.");
            $this->command?->info("{$ingredientLinks} recipe ingredient links created.");
        });
    }
}