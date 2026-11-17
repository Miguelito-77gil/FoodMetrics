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

class DeliveryStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $staff, string $status = 'assigned'): DeliveryDetail
    {
        $creator = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $creator->id,
            'order_type' => 'delivery',
            'status' => match ($status) {
                'assigned' => 'ready',
                'delivered' => 'completed',
                default => 'out_for_delivery',
            },
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 50,
            'total_amount' => 150,
            'ordered_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Delivery Status Test ' . Str::random(6),
            'slug' => 'delivery-status-' . Str::lower(Str::random(8)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'product_code' => 'DST-' . strtoupper(Str::random(8)),
            'name' => 'Delivery Status Test Product',
            'slug' => 'delivery-status-product-' . Str::lower(Str::random(8)),
            'base_price' => 100,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Delivery Status Test Ingredient ' . Str::random(5),
            'base_unit' => 'kg',
            'current_stock' => 5,
            'minimum_stock' => 1,
            'cost_per_base_unit' => 100,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'name' => 'Delivery Status Test Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.250,
            'unit' => 'kg',
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
        return DeliveryDetail::create([
            'order_id' => $order->id,
            'delivery_staff_id' => $staff->id,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Test Delivery Address',
            'delivery_fee' => 50,
            'status' => $status,
        ]);
    }

    public function test_assigned_staff_can_mark_delivery_as_picked_up(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff);

        $response = $this->actingAs($staff)->patch(
            route('delivery.status.update', $delivery),
            [
                'status' => 'picked_up',
                'remarks' => 'Package collected.',
            ]
        );

        $response->assertRedirect(route('delivery.show', $delivery));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'picked_up',
        ]);

        $this->assertNotNull($delivery->fresh()->picked_up_at);

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $delivery->id,
            'updated_by' => $staff->id,
            'previous_status' => 'assigned',
            'new_status' => 'picked_up',
            'remarks' => 'Package collected.',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $delivery->order_id,
            'status' => 'out_for_delivery',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $delivery->order_id,
            'previous_status' => 'ready',
            'new_status' => 'out_for_delivery',
            'updated_by' => $staff->id,
        ]);
    }

    public function test_assigned_staff_can_mark_picked_up_delivery_as_delivered(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff, 'picked_up');

        $this->actingAs($staff)->patch(
            route('delivery.status.update', $delivery),
            [
                'status' => 'delivered',
                'remarks' => 'Successfully delivered.',
            ]
        )->assertRedirect(route('delivery.show', $delivery));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'delivered',
        ]);

        $this->assertNotNull($delivery->fresh()->delivered_at);

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $delivery->id,
            'previous_status' => 'picked_up',
            'new_status' => 'delivered',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $delivery->order_id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $delivery->order_id,
            'previous_status' => 'out_for_delivery',
            'new_status' => 'completed',
            'updated_by' => $staff->id,
        ]);
    }

    public function test_failure_requires_a_reason(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff, 'picked_up');

        $this->actingAs($staff)->patch(
            route('delivery.status.update', $delivery),
            ['status' => 'failed']
        )->assertSessionHasErrors('failure_reason');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'picked_up',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_staff_can_mark_delivery_as_failed_with_a_reason(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff, 'picked_up');

        $this->actingAs($staff)->patch(
            route('delivery.status.update', $delivery),
            [
                'status' => 'failed',
                'failure_reason' => 'Recipient was unavailable.',
            ]
        )->assertRedirect(route('delivery.show', $delivery));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'failed',
            'failure_reason' => 'Recipient was unavailable.',
        ]);

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $delivery->id,
            'previous_status' => 'picked_up',
            'new_status' => 'failed',
        ]);
    }

    public function test_staff_cannot_update_another_staff_delivery(): void
    {
        $staffA = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $staffB = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staffB);

        $this->actingAs($staffA)->patch(
            route('delivery.status.update', $delivery),
            ['status' => 'picked_up']
        )->assertForbidden();

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_terminal_delivery_status_cannot_be_changed_again(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff, 'delivered');

        $this->actingAs($staff)->patch(
            route('delivery.status.update', $delivery),
            ['status' => 'picked_up']
        )->assertSessionHasErrors('status');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_admin_can_update_an_assigned_delivery(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff);

        $this->actingAs($admin)->patch(
            route('delivery.status.update', $delivery),
            ['status' => 'picked_up']
        )->assertRedirect(route('delivery.show', $delivery));

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'status' => 'picked_up',
        ]);

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $delivery->id,
            'updated_by' => $admin->id,
            'new_status' => 'picked_up',
        ]);
    }
}