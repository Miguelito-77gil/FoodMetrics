<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\AddOnIngredient;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddOn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\InventoryConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryConsumptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Test Category ' . Str::random(6),
            'slug' => 'test-category-' . Str::lower(Str::random(8)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'product_code' => 'TEST-' . strtoupper(Str::random(8)),
            'name' => 'Test Product',
            'slug' => 'test-product-' . Str::lower(Str::random(8)),
            'base_price' => 100,
            'is_available' => true,
            'is_featured' => false,
            'preparation_time' => 10,
        ]);
    }

    private function makeIngredient(
        string $name,
        string $unit = 'kg',
        float $stock = 10,
        float $cost = 100
    ): Ingredient {
        return Ingredient::create([
            'name' => $name,
            'base_unit' => $unit,
            'current_stock' => $stock,
            'minimum_stock' => 1,
            'cost_per_base_unit' => $cost,
            'is_active' => true,
        ]);
    }

    private function makeOrder(
        Product $product,
        int $quantity = 1,
        ?ProductVariant $variant = null
    ): array {
        $user = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $user->id,
            'order_type' => 'takeout',
            'status' => 'confirmed',
            'subtotal' => 100 * $quantity,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 100 * $quantity,
            'ordered_at' => now(),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'subtotal' => 100 * $quantity,
        ]);

        return [$order, $item, $user];
    }

    private function makeRecipe(
        Product $product,
        Ingredient $ingredient,
        float $quantityRequired,
        string $unit = 'kg',
        ?ProductVariant $variant = null
    ): Recipe {
        $recipe = Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'name' => 'Test Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => $quantityRequired,
            'unit' => $unit,
        ]);

        return $recipe;
    }

    private function assertInventoryFailure(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
            return;
        }

        $this->fail('Expected an inventory validation exception.');
    }

    public function test_product_recipe_deducts_correct_quantity_and_creates_movement(): void
    {
        $product = $this->makeProduct();
        $ingredient = $this->makeIngredient('White Rice', 'kg', 5, 80);

        $this->makeRecipe($product, $ingredient, 0.250);

        [$order, , $user] = $this->makeOrder($product, 2);

        app(InventoryConsumptionService::class)
            ->consumeForOrder($order->id, $user->id);

        $this->assertEquals(
            4.500,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $ingredient->id,
            'user_id' => $user->id,
            'movement_type' => 'stock_out',
            'quantity' => '0.500',
            'stock_before' => '5.000',
            'stock_after' => '4.500',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_add_on_quantity_is_multiplied_and_shared_ingredients_are_combined(): void
    {
        $product = $this->makeProduct();

        $rice = $this->makeIngredient('White Rice', 'kg', 5);
        $garlic = $this->makeIngredient('Garlic', 'kg', 5);

        $this->makeRecipe($product, $rice, 0.120);

        $addOn = AddOn::create([
            'name' => 'Garlic Rice',
            'description' => 'Test add-on',
            'price' => 35,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $rice->id,
            'quantity_required' => 0.120,
            'unit' => 'kg',
        ]);

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $garlic->id,
            'quantity_required' => 0.008,
            'unit' => 'kg',
        ]);

        [$order, $item, $user] = $this->makeOrder($product, 2);

        OrderItemAddOn::create([
            'order_item_id' => $item->id,
            'add_on_id' => $addOn->id,
            'add_on_name' => $addOn->name,
            'quantity' => 2,
            'unit_price' => 35,
            'subtotal' => 140,
        ]);

        app(InventoryConsumptionService::class)
            ->consumeForOrder($order->id, $user->id);

        // Rice: (2 x 0.120) + (2 x 2 x 0.120) = 0.720 kg.
        $this->assertEquals(
            4.280,
            (float) $rice->fresh()->current_stock
        );

        // Garlic: 2 x 2 x 0.008 = 0.032 kg.
        $this->assertEquals(
            4.968,
            (float) $garlic->fresh()->current_stock
        );

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $rice->id,
            'quantity' => '0.720',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $garlic->id,
            'quantity' => '0.032',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);

        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_variant_order_uses_exact_variant_recipe(): void
    {
        $product = $this->makeProduct();

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'TEST-' . strtoupper(Str::random(8)),
            'price_adjustment' => 20,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $baseIngredient = $this->makeIngredient('Base Ingredient', 'kg', 5);
        $variantIngredient = $this->makeIngredient('Variant Ingredient', 'kg', 5);

        $this->makeRecipe($product, $baseIngredient, 0.100);
        $this->makeRecipe(
            $product,
            $variantIngredient,
            0.300,
            'kg',
            $variant
        );

        [$order, , $user] = $this->makeOrder($product, 2, $variant);

        app(InventoryConsumptionService::class)
            ->consumeForOrder($order->id, $user->id);

        $this->assertEquals(
            5.000,
            (float) $baseIngredient->fresh()->current_stock
        );

        $this->assertEquals(
            4.400,
            (float) $variantIngredient->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $variantIngredient->id,
            'quantity' => '0.600',
            'reference_id' => $order->id,
        ]);
    }

    public function test_missing_variant_recipe_does_not_fall_back_to_base_recipe(): void
    {
        $product = $this->makeProduct();

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'TEST-' . strtoupper(Str::random(8)),
            'price_adjustment' => 20,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $ingredient = $this->makeIngredient('Base Ingredient', 'kg', 5);

        $this->makeRecipe($product, $ingredient, 0.100);

        [$order, , $user] = $this->makeOrder($product, 1, $variant);

        $this->assertInventoryFailure(function () use ($order, $user) {
            app(InventoryConsumptionService::class)
                ->consumeForOrder($order->id, $user->id);
        });

        $this->assertEquals(
            5.000,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_insufficient_stock_prevents_all_deductions(): void
    {
        $product = $this->makeProduct();

        $first = $this->makeIngredient('Ingredient A', 'kg', 10);
        $second = $this->makeIngredient('Ingredient B', 'kg', 0.100);

        $recipe = Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'name' => 'Two Ingredient Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $first->id,
            'quantity_required' => 0.500,
            'unit' => 'kg',
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $second->id,
            'quantity_required' => 0.500,
            'unit' => 'kg',
        ]);

        [$order, , $user] = $this->makeOrder($product, 1);

        $this->assertInventoryFailure(function () use ($order, $user) {
            app(InventoryConsumptionService::class)
                ->consumeForOrder($order->id, $user->id);
        });

        $this->assertEquals(10.000, (float) $first->fresh()->current_stock);
        $this->assertEquals(0.100, (float) $second->fresh()->current_stock);

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_add_on_without_ingredient_mapping_is_rejected(): void
    {
        $product = $this->makeProduct();
        $ingredient = $this->makeIngredient('White Rice', 'kg', 5);

        $this->makeRecipe($product, $ingredient, 0.100);

        $addOn = AddOn::create([
            'name' => 'Unmapped Add-on',
            'description' => 'Test add-on',
            'price' => 20,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        [$order, $item, $user] = $this->makeOrder($product, 1);

        OrderItemAddOn::create([
            'order_item_id' => $item->id,
            'add_on_id' => $addOn->id,
            'add_on_name' => $addOn->name,
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
        ]);

        $this->assertInventoryFailure(function () use ($order, $user) {
            app(InventoryConsumptionService::class)
                ->consumeForOrder($order->id, $user->id);
        });

        $this->assertEquals(5.000, (float) $ingredient->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_duplicate_inventory_deduction_is_rejected(): void
    {
        $product = $this->makeProduct();
        $ingredient = $this->makeIngredient('White Rice', 'kg', 5);

        $this->makeRecipe($product, $ingredient, 0.250);

        [$order, , $user] = $this->makeOrder($product, 2);

        $service = app(InventoryConsumptionService::class);

        $service->consumeForOrder($order->id, $user->id);

        $this->assertEquals(
            4.500,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertInventoryFailure(function () use ($service, $order, $user) {
            $service->consumeForOrder($order->id, $user->id);
        });

        $this->assertEquals(
            4.500,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_unit_mismatch_is_rejected_without_deduction(): void
    {
        $product = $this->makeProduct();
        $ingredient = $this->makeIngredient('White Rice', 'kg', 5);

        $this->makeRecipe($product, $ingredient, 250, 'g');

        [$order, , $user] = $this->makeOrder($product, 1);

        $this->assertInventoryFailure(function () use ($order, $user) {
            app(InventoryConsumptionService::class)
                ->consumeForOrder($order->id, $user->id);
        });

        $this->assertEquals(
            5.000,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_order_consumption_creates_low_stock_recommendation_after_deduction(): void
    {
        $product = $this->makeProduct();
        $ingredient = $this->makeIngredient('White Rice', 'kg', 1, 100);

        $this->makeRecipe($product, $ingredient, 0.250);

        [$order, , $user] = $this->makeOrder($product, 2);

        app(InventoryConsumptionService::class)
            ->consumeForOrder($order->id, $user->id);

        $this->assertEquals(
            0.500,
            (float) $ingredient->fresh()->current_stock
        );

        $this->assertDatabaseHas('inventory_recommendations', [
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => null,
            'current_stock' => '0.500',
            'recommended_quantity' => '1.000',
            'estimated_cost' => '100.00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('inventory_recommendations', 1);
    }}