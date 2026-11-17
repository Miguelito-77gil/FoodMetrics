<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Order Test Cashier',
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ODT-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => 'preparing',
            'subtotal' => 500,
            'discount_amount' => 25,
            'tax_amount' => 50,
            'delivery_fee' => 0,
            'total_amount' => 525,
            'notes' => 'Order details test note.',
            'ordered_at' => now(),
        ]);

        $firstHistory = OrderStatusHistory::create([
            'order_id' => $order->id,
            'updated_by' => $cashier->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
            'remarks' => 'Order confirmed.',
            'changed_at' => now()->subMinutes(10),
        ]);

        $secondHistory = OrderStatusHistory::create([
            'order_id' => $order->id,
            'updated_by' => $cashier->id,
            'previous_status' => 'confirmed',
            'new_status' => 'preparing',
            'remarks' => 'Preparation started.',
            'changed_at' => now(),
        ]);

        return compact(
            'cashier',
            'order',
            'firstHistory',
            'secondHistory'
        );
    }

    public function test_admin_can_view_order_details_and_status_history(): void
    {
        $data = $this->makeOrder();

        $admin = User::factory()->create([
            'name' => 'Order Test Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('orders.show', $data['order']))
            ->assertOk()
            ->assertSee('Order Details')
            ->assertSee($data['order']->order_number)
            ->assertSee('Order Test Cashier')
            ->assertSee('Customer Information')
            ->assertSee('Ordered Items')
            ->assertSee('Financial Summary')
            ->assertSee('Payments')
            ->assertSee('Record Payment')
            ->assertSee('Order Status Timeline')
            ->assertSee('Pending')
            ->assertSee('Confirmed')
            ->assertSee('Preparing')
            ->assertSee('Order confirmed.')
            ->assertSee('Preparation started.');
    }

    public function test_cashier_can_view_order_details(): void
    {
        $data = $this->makeOrder();

        $this->actingAs($data['cashier'])
            ->get(route('orders.show', $data['order']))
            ->assertOk()
            ->assertSee('Order Details')
            ->assertSee($data['order']->order_number)
            ->assertSee('Order Status Timeline');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $data = $this->makeOrder();

        $this->get(route('orders.show', $data['order']))
            ->assertRedirect(route('login'));
    }

    public function test_inventory_staff_cannot_view_order_details(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('orders.show', $data['order']))
            ->assertForbidden();
    }

    public function test_delivery_staff_cannot_view_order_details(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('orders.show', $data['order']))
            ->assertForbidden();
    }

    public function test_viewing_order_details_does_not_modify_order_or_history(): void
    {
        $data = $this->makeOrder();

        $originalHistory = OrderStatusHistory::query()
            ->where('order_id', $data['order']->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($history) => [
                'id' => $history->id,
                'updated_by' => $history->updated_by,
                'previous_status' => $history->previous_status,
                'new_status' => $history->new_status,
                'remarks' => $history->remarks,
                'changed_at' => $history->changed_at?->toDateTimeString(),
            ])
            ->all();

        $this->actingAs($data['cashier'])
            ->get(route('orders.show', $data['order']))
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'preparing',
            'total_amount' => 525,
        ]);

        $this->assertDatabaseCount('order_status_history', 2);

        $currentHistory = OrderStatusHistory::query()
            ->where('order_id', $data['order']->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($history) => [
                'id' => $history->id,
                'updated_by' => $history->updated_by,
                'previous_status' => $history->previous_status,
                'new_status' => $history->new_status,
                'remarks' => $history->remarks,
                'changed_at' => $history->changed_at?->toDateTimeString(),
            ])
            ->all();

        $this->assertSame($originalHistory, $currentHistory);
    }

    public function test_admin_can_open_order_management_list(): void
    {
        $data = $this->makeOrder();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Order Management')
            ->assertSee($data['order']->order_number)
            ->assertSee('View Details');
    }

    public function test_cashier_can_open_order_management_list(): void
    {
        $data = $this->makeOrder();

        $this->actingAs($data['cashier'])
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Order Management')
            ->assertSee($data['order']->order_number);
    }

    public function test_cashier_homepage_redirects_to_order_management(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->actingAs($cashier)
            ->get(route('cashier.index'))
            ->assertRedirect(route('orders.index'));
    }

    public function test_inventory_and_delivery_staff_cannot_open_order_list(): void
    {
        foreach (['inventory_staff', 'delivery_staff'] as $role) {
            $staff = User::factory()->create([
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($staff)
                ->get(route('orders.index'))
                ->assertForbidden();
        }
    }
    public function test_cashier_can_see_payment_form_for_partially_paid_order(): void
    {
        $data = $this->makeOrder();

        Payment::create([
            'payment_number' => 'PAY-TEST-' . Str::random(12),
            'order_id' => $data['order']->id,
            'received_by' => $data['cashier']->id,
            'payment_method' => 'cash',
            'amount' => 100,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->actingAs($data['cashier'])
            ->get(route('orders.show', $data['order']))
            ->assertOk()
            ->assertSee('Partially paid')
            ->assertSee('Record Payment');
    }

    public function test_payment_form_is_hidden_for_fully_paid_order(): void
    {
        $data = $this->makeOrder();

        Payment::create([
            'payment_number' => 'PAY-TEST-' . Str::random(12),
            'order_id' => $data['order']->id,
            'received_by' => $data['cashier']->id,
            'payment_method' => 'cash',
            'amount' => 525,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->actingAs($data['cashier'])
            ->get(route('orders.show', $data['order']))
            ->assertOk()
            ->assertSee('Payment Status')
            ->assertSee('Paid')
            ->assertDontSee('Record Payment');
    }

    public function test_payment_form_is_hidden_for_cancelled_order(): void
    {
        $data = $this->makeOrder();

        $data['order']->update([
            'status' => 'cancelled',
        ]);

        $this->actingAs($data['cashier'])
            ->get(route('orders.show', $data['order']))
            ->assertOk()
            ->assertDontSee('Record Payment');
    }
}