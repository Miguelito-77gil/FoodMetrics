<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(
        string $type = 'takeout',
        string $status = 'ready',
        float $stock = 5.000
    ): array {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Order Integration ' . Str::random(6),
            'slug' => 'order-integration-' . Str::lower(Str::random(8)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'product_code' => 'OIT-' . strtoupper(Str::random(8)),
            'name' => 'Order Integration Product',
            'slug' => 'order-integration-' . Str::lower(Str::random(8)),
            'base_price' => 100,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Order Integration Ingredient ' . Str::random(5),
            'base_unit' => 'kg',
            'current_stock' => $stock,
            'minimum_stock' => 1,
            'cost_per_base_unit' => 100,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'name' => 'Order Integration Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.250,
            'unit' => 'kg',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => $type,
            'status' => $status,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'variant_name' => null,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
        ]);

        return compact('cashier', 'order', 'ingredient');
    }

    public function test_cashier_can_complete_takeout_order_and_deduct_inventory(): void
    {
        $data = $this->makeOrder('takeout', 'ready', 5.000);

        $this->actingAs($data['cashier'])
            ->from('/cashier')
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertRedirect('/cashier')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($data['order']->fresh()->completed_at);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $data['order']->id,
            'updated_by' => $data['cashier']->id,
            'previous_status' => 'ready',
            'new_status' => 'completed',
        ]);

        $this->assertEquals(
            4.750,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $data['ingredient']->id,
            'movement_type' => 'stock_out',
            'reference_type' => 'order',
            'reference_id' => $data['order']->id,
            'quantity' => '0.250',
        ]);
    }

    public function test_admin_can_complete_dine_in_order_and_deduct_inventory(): void
    {
        $data = $this->makeOrder('dine_in', 'ready', 3.000);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $data['order']->update([
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from('/cashier')
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertRedirect('/cashier');

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(
            2.750,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $data['order']->id,
            'updated_by' => $admin->id,
            'previous_status' => 'ready',
            'new_status' => 'completed',
        ]);
    }

    public function test_insufficient_stock_rolls_back_order_completion_and_inventory(): void
    {
        $data = $this->makeOrder('takeout', 'ready', 0.100);

        $this->actingAs($data['cashier'])
            ->from('/cashier')
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertRedirect('/cashier')
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'ready',
            'completed_at' => null,
        ]);

        $this->assertEquals(
            0.100,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_order_cannot_be_completed_before_ready_status(): void
    {
        $data = $this->makeOrder('takeout', 'preparing', 5.000);

        $this->actingAs($data['cashier'])
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'preparing',
            'completed_at' => null,
        ]);

        $this->assertEquals(
            5.000,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_delivery_order_cannot_use_cashier_completion_endpoint(): void
    {
        $data = $this->makeOrder('delivery', 'ready', 5.000);

        $this->actingAs($data['cashier'])
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'ready',
        ]);

        $this->assertEquals(
            5.000,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_delivery_staff_cannot_complete_takeout_orders(): void
    {
        $data = $this->makeOrder('takeout', 'ready', 5.000);

        $deliveryStaff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->actingAs($deliveryStaff)
            ->post(route('cashier.orders.complete', $data['order']))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'ready',
        ]);

        $this->assertEquals(
            5.000,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }
}