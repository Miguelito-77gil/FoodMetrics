<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'pending'): array
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'OST-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => $status,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'ordered_at' => now(),
        ]);

        return compact('cashier', 'order');
    }

    public function test_cashier_can_complete_all_preparation_status_transitions_in_order(): void
    {
        $data = $this->makeOrder();

        $transitions = [
            ['pending', 'confirmed'],
            ['confirmed', 'preparing'],
            ['preparing', 'ready'],
        ];

        foreach ($transitions as [$previous, $next]) {
            $this->actingAs($data['cashier'])
                ->from('/cashier')
                ->patch(
                    route('cashier.orders.status.update', $data['order']),
                    [
                        'status' => $next,
                        'remarks' => 'Test transition.',
                    ]
                )
                ->assertRedirect('/cashier')
                ->assertSessionHas('success');

            $this->assertDatabaseHas('orders', [
                'id' => $data['order']->id,
                'status' => $next,
            ]);

            $this->assertDatabaseHas('order_status_history', [
                'order_id' => $data['order']->id,
                'updated_by' => $data['cashier']->id,
                'previous_status' => $previous,
                'new_status' => $next,
                'remarks' => 'Test transition.',
            ]);
        }

        $this->assertDatabaseCount('order_status_history', 3);
    }

    public function test_admin_can_confirm_pending_order(): void
    {
        $data = $this->makeOrder();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'confirmed']
            )
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $data['order']->id,
            'updated_by' => $admin->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
        ]);
    }

    public function test_order_cannot_skip_confirmation_and_preparation(): void
    {
        $data = $this->makeOrder('pending');

        $this->actingAs($data['cashier'])
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'ready']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_order_cannot_move_backwards_from_ready(): void
    {
        $data = $this->makeOrder('ready');

        $this->actingAs($data['cashier'])
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'confirmed']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'ready',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_completed_order_cannot_use_preparation_transition_endpoint(): void
    {
        $data = $this->makeOrder('completed');

        $this->actingAs($data['cashier'])
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'confirmed']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_invalid_target_status_is_rejected(): void
    {
        $data = $this->makeOrder();

        $this->actingAs($data['cashier'])
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'cancelled']
            )
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_inventory_staff_cannot_update_order_status(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'confirmed']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_delivery_staff_cannot_update_order_status(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->patch(
                route('cashier.orders.status.update', $data['order']),
                ['status' => 'confirmed']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_guest_cannot_update_order_status(): void
    {
        $data = $this->makeOrder();

        $this->patch(
            route('cashier.orders.status.update', $data['order']),
            ['status' => 'confirmed']
        )->assertRedirect(route('login'));

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_status_history', 0);
    }
}