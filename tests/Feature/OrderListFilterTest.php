<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderListFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create([
            'name' => 'Order Filter Cashier',
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    private function makeOrder(array $attributes = []): Order
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-' . strtoupper(Str::random(10)),
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => Str::random(10) . '@example.com',
            'phone' => '09170000000',
            'is_active' => true,
        ]);

        return Order::create(array_merge([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'customer_id' => $customer->id,
            'user_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'status' => 'pending',
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'ordered_at' => now(),
        ], $attributes));
    }

    public function test_cashier_can_search_orders_by_order_number(): void
    {
        $matching = $this->makeOrder([
            'order_number' => 'OGK-SEARCH-001',
        ]);

        $other = $this->makeOrder([
            'order_number' => 'OGK-OTHER-002',
        ]);

        $this->actingAs($this->cashier)
            ->get(route('orders.index', ['search' => 'OGK-SEARCH-001']))
            ->assertOk()
            ->assertSee($matching->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_cashier_can_search_orders_by_customer_name(): void
    {
        $matching = $this->makeOrder();
        $other = $this->makeOrder();

        $this->actingAs($this->cashier)
            ->get(route('orders.index', ['search' => 'Maria']))
            ->assertOk()
            ->assertSee($matching->order_number)
            ->assertSee($other->order_number);
    }

    public function test_cashier_can_filter_orders_by_status(): void
    {
        $matching = $this->makeOrder([
            'status' => 'preparing',
        ]);

        $other = $this->makeOrder([
            'status' => 'pending',
        ]);

        $this->actingAs($this->cashier)
            ->get(route('orders.index', ['status' => 'preparing']))
            ->assertOk()
            ->assertSee($matching->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_cashier_can_filter_orders_by_order_type(): void
    {
        $matching = $this->makeOrder([
            'order_type' => 'delivery',
        ]);

        $other = $this->makeOrder([
            'order_type' => 'takeout',
        ]);

        $this->actingAs($this->cashier)
            ->get(route('orders.index', ['type' => 'delivery']))
            ->assertOk()
            ->assertSee($matching->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_cashier_can_filter_orders_by_date_range(): void
    {
        $matching = $this->makeOrder([
            'ordered_at' => now()->subDays(2),
        ]);

        $older = $this->makeOrder([
            'ordered_at' => now()->subDays(10),
        ]);

        $this->actingAs($this->cashier)
            ->get(route('orders.index', [
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($matching->order_number)
            ->assertDontSee($older->order_number);
    }

    public function test_filter_values_are_preserved_in_the_response(): void
    {
        $this->makeOrder([
            'order_number' => 'OGK-PRESERVE-001',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->cashier)
            ->get(route('orders.index', [
                'search' => 'OGK-PRESERVE',
                'status' => 'confirmed',
                'type' => 'takeout',
            ]))
            ->assertOk()
            ->assertSee('OGK-PRESERVE')
            ->assertSee('value="OGK-PRESERVE"', false)
            ->assertSee('value="confirmed" selected', false)
            ->assertSee('value="takeout" selected', false);
    }
}