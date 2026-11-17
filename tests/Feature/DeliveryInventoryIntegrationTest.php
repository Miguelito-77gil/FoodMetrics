<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryDetail;
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

class DeliveryInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveryWithRecipe(
        float $stock = 5,
        float $requiredQuantity = 0.250,
        string $deliveryStatus = 'picked_up'
    ): array {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Delivery Test ' . Str::random(6),
            'slug' => 'delivery-test-' . Str::lower(Str::random(8)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'product_code' => 'DEL-' . strtoupper(Str::random(8)),
            'name' => 'Delivery Test Product',
            'slug' => 'delivery-product-' . Str::lower(Str::random(8)),
            'base_price' => 100,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Delivery Test Ingredient ' . Str::random(5),
            'base_unit' => 'kg',
            'current_stock' => $stock,
            'minimum_stock' => 1,
            'cost_per_base_unit' => 100,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'name' => 'Delivery Test Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => $requiredQuantity,
            'unit' => 'kg',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'delivery',
            'status' => match ($deliveryStatus) {
                'assigned' => 'ready',
                'delivered' => 'completed',
                default => 'out_for_delivery',
            },
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 50,
            'total_amount' => 250,
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'variant_name' => null,
            'quantity' => 2,
            'unit_price' => 100,
            'subtotal' => 200,
        ]);

        $delivery = DeliveryDetail::create([
            'order_id' => $order->id,
            'delivery_staff_id' => $staff->id,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Test Delivery Address',
            'delivery_fee' => 50,
            'status' => $deliveryStatus,
        ]);

        return compact(
            'cashier',
            'staff',
            'product',
            'ingredient',
            'order',
            'delivery'
        );
    }

    public function test_successful_delivery_completes_order_and_deducts_inventory(): void
    {
        $data = $this->makeDeliveryWithRecipe();

        $this->actingAs($data['staff'])->patch(
            route('delivery.status.update', $data['delivery']),
            [
                'status' => 'delivered',
                'remarks' => 'Successfully delivered.',
            ]
        )->assertRedirect(route('delivery.show', $data['delivery']));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $data['delivery']->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($data['order']->fresh()->completed_at);

        // 2 ordered items x 0.250 kg = 0.500 kg.
        $this->assertEquals(
            4.500,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $data['ingredient']->id,
            'movement_type' => 'stock_out',
            'quantity' => '0.500',
            'reference_type' => 'order',
            'reference_id' => $data['order']->id,
        ]);

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $data['delivery']->id,
            'previous_status' => 'picked_up',
            'new_status' => 'delivered',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $data['order']->id,
            'previous_status' => 'out_for_delivery',
            'new_status' => 'completed',
            'updated_by' => $data['staff']->id,
        ]);
    }

    public function test_insufficient_stock_rolls_back_delivery_order_and_history(): void
    {
        $data = $this->makeDeliveryWithRecipe(0.100, 0.250);

        $this->actingAs($data['staff'])->patch(
            route('delivery.status.update', $data['delivery']),
            [
                'status' => 'delivered',
                'remarks' => 'Attempted delivery.',
            ]
        )->assertSessionHasErrors('inventory');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $data['delivery']->id,
            'status' => 'picked_up',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'out_for_delivery',
            'completed_at' => null,
        ]);

        $this->assertEquals(
            0.100,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('delivery_status_history', 0);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_picking_up_delivery_does_not_complete_order_or_deduct_inventory(): void
    {
        $data = $this->makeDeliveryWithRecipe(
            5,
            0.250,
            'assigned'
        );

        $this->actingAs($data['staff'])->patch(
            route('delivery.status.update', $data['delivery']),
            [
                'status' => 'picked_up',
            ]
        )->assertRedirect(route('delivery.show', $data['delivery']));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $data['delivery']->id,
            'status' => 'picked_up',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'out_for_delivery',
            'completed_at' => null,
        ]);

        $this->assertEquals(
            5.000,
            (float) $data['ingredient']->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $data['order']->id,
            'previous_status' => 'ready',
            'new_status' => 'out_for_delivery',
            'updated_by' => $data['staff']->id,
        ]);
    }
    public function test_duplicate_delivery_completion_does_not_deduct_inventory_twice(): void
    {
        $data = $this->makeDeliveryWithRecipe();

        // First request: delivery completes successfully.
        $this->actingAs($data['staff'])->patch(
            route('delivery.status.update', $data['delivery']),
            ['status' => 'delivered']
        )->assertRedirect(route('delivery.show', $data['delivery']));

        $this->assertEquals(
            4.500,
            (float) $data['ingredient']->fresh()->current_stock
        );

        // Second request: duplicate completion must be rejected.
        $this->patch(
            route('delivery.status.update', $data['delivery']),
            ['status' => 'delivered']
        )->assertSessionHasErrors('status');

        // Order and delivery must remain completed/delivered.
        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('delivery_details', [
            'id' => $data['delivery']->id,
            'status' => 'delivered',
        ]);

        // Stock must not be deducted a second time.
        $this->assertEquals(
            4.500,
            (float) $data['ingredient']->fresh()->current_stock
        );

        // Exactly one inventory movement and one history entry each.
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('delivery_status_history', 1);
        $this->assertDatabaseCount('order_status_history', 1);
    }
}