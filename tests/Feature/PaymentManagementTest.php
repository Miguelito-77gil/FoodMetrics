<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'preparing'): array
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'PMT-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => $status,
            'subtotal' => 500,
            'discount_amount' => 25,
            'tax_amount' => 50,
            'delivery_fee' => 0,
            'total_amount' => 525,
            'notes' => 'Payment management test.',
            'ordered_at' => now(),
        ]);

        return compact('cashier', 'order');
    }

    private function createPayment(
        Order $order,
        User $receiver,
        float $amount,
        string $status = 'completed'
    ): Payment {
        return Payment::create([
            'payment_number' => 'PMT-' . Str::uuid(),
            'order_id' => $order->id,
            'received_by' => $receiver->id,
            'payment_method' => 'cash',
            'amount' => number_format($amount, 2, '.', ''),
            'status' => $status,
            'paid_at' => now(),
        ]);
    }

    private function paymentData(float $amount): array
    {
        return [
            'payment_method' => 'cash',
            'amount' => $amount,
            'transaction_reference' => null,
            'notes' => 'Payment test.',
        ];
    }

    public function test_cashier_can_record_multiple_partial_payments(): void
    {
        $data = $this->makeOrder();
        $url = route('cashier.orders.payments.store', $data['order']);

        $this->actingAs($data['cashier'])
            ->post($url, $this->paymentData(200))
            ->assertRedirect();

        $this->actingAs($data['cashier'])
            ->post($url, $this->paymentData(325))
            ->assertRedirect();

        $this->assertDatabaseCount('payments', 2);

        $this->assertDatabaseHas('payments', [
            'order_id' => $data['order']->id,
            'amount' => '200.00',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $data['order']->id,
            'amount' => '325.00',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'preparing',
        ]);
    }

    public function test_admin_can_record_a_payment(): void
    {
        $data = $this->makeOrder();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(100)
            )
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $data['order']->id,
            'received_by' => $admin->id,
            'amount' => '100.00',
            'status' => 'completed',
        ]);
    }

    public function test_overpayment_is_rejected(): void
    {
        $data = $this->makeOrder();

        $this->actingAs($data['cashier'])
            ->from(route('orders.show', $data['order']))
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(526)
            )
            ->assertRedirect(route('orders.show', $data['order']))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_additional_payment_is_rejected_when_order_is_fully_paid(): void
    {
        $data = $this->makeOrder();

        $this->createPayment(
            $data['order'],
            $data['cashier'],
            525
        );

        $this->actingAs($data['cashier'])
            ->from(route('orders.show', $data['order']))
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(1)
            )
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cancelled_order_cannot_receive_payment(): void
    {
        $data = $this->makeOrder('cancelled');

        $this->actingAs($data['cashier'])
            ->from(route('orders.show', $data['order']))
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(100)
            )
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_refunded_payments_are_excluded_from_paid_balance(): void
    {
        $data = $this->makeOrder();

        $this->createPayment(
            $data['order'],
            $data['cashier'],
            100,
            'refunded'
        );

        $response = $this->actingAs($data['cashier'])
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(425)
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $data['order']->id,
            'amount' => '425.00',
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('payments', 2);
    }

    public function test_inventory_staff_cannot_record_payment(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(100)
            )
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_delivery_staff_cannot_record_payment(): void
    {
        $data = $this->makeOrder();

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(
                route('cashier.orders.payments.store', $data['order']),
                $this->paymentData(100)
            )
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $data = $this->makeOrder();

        $this->post(
            route('cashier.orders.payments.store', $data['order']),
            $this->paymentData(100)
        )->assertRedirect(route('login'));

        $this->assertDatabaseCount('payments', 0);
    }
}