<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeOrder(
        User $cashier,
        string $status = 'pending',
        ?string $orderedAt = null
    ): Order {
        return Order::create([
            'order_number' => 'AN-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => $status,
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'notes' => 'Analytics test.',
            'ordered_at' => $orderedAt ?? now(),
        ]);
    }

    private function makePayment(
        Order $order,
        User $receiver,
        float $amount,
        string $status = 'completed',
        ?string $paidAt = null
    ): Payment {
        return Payment::create([
            'payment_number' => 'AN-' . Str::uuid(),
            'order_id' => $order->id,
            'received_by' => $receiver->id,
            'payment_method' => 'cash',
            'amount' => number_format($amount, 2, '.', ''),
            'status' => $status,
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    private function makeProduct(string $name): Product
    {
        $suffix = Str::lower(Str::random(8));

        $category = Category::create([
            'name' => 'Analytics Category ' . $suffix,
            'slug' => 'analytics-category-' . $suffix,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'product_code' => 'AN-' . strtoupper(Str::random(8)),
            'name' => $name,
            'slug' => 'analytics-' . $suffix,
            'base_price' => 100,
            'is_available' => true,
            'is_featured' => false,
        ]);
    }

    private function addOrderItem(
        Order $order,
        Product $product,
        int $quantity,
        float $subtotal
    ): void {
        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_revenue_counts_completed_partial_payments_and_distinct_paid_orders(): void
    {
        $admin = $this->makeAdmin();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $paidOrder = $this->makeOrder($cashier);
        $unpaidOrder = $this->makeOrder($cashier);

        $this->makePayment($paidOrder, $cashier, 200);
        $this->makePayment($paidOrder, $cashier, 300);
        $this->makePayment($unpaidOrder, $cashier, 150, 'pending');

        $response = $this->actingAs($admin)
            ->get(route('admin.index') . '?range=today');

        $response->assertOk()
            ->assertViewHas('revenue', 500.0)
            ->assertViewHas('paidOrderCount', 1)
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('averageCollectedPerPaidOrder', 500.0);
    }

    public function test_failed_and_refunded_payments_are_excluded_from_revenue(): void
    {
        $admin = $this->makeAdmin();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = $this->makeOrder($cashier);

        $this->makePayment($order, $cashier, 100, 'completed');
        $this->makePayment($order, $cashier, 200, 'failed');
        $this->makePayment($order, $cashier, 300, 'refunded');

        $this->actingAs($admin)
            ->get(route('admin.index') . '?range=today')
            ->assertOk()
            ->assertViewHas('revenue', 100.0)
            ->assertViewHas('paidOrderCount', 1);
    }

    public function test_best_sellers_exclude_cancelled_orders_and_rank_by_quantity(): void
    {
        $admin = $this->makeAdmin();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $productA = $this->makeProduct('Analytics Product A');
        $productB = $this->makeProduct('Analytics Product B');

        $validOrder = $this->makeOrder($cashier, 'completed');
        $cancelledOrder = $this->makeOrder($cashier, 'cancelled');

        $this->addOrderItem($validOrder, $productA, 3, 300);
        $this->addOrderItem($validOrder, $productB, 5, 500);

        // This cancelled order must not affect product rankings.
        $this->addOrderItem($cancelledOrder, $productA, 50, 5000);

        $this->actingAs($admin)
            ->get(route('admin.index') . '?range=today')
            ->assertOk()
            ->assertViewHas('bestSellers', function ($products) {
                $productA = $products->firstWhere(
                    'product_name',
                    'Analytics Product A'
                );

                $productB = $products->firstWhere(
                    'product_name',
                    'Analytics Product B'
                );

                return $productA !== null
                    && $productB !== null
                    && (int) $productA->total_quantity === 3
                    && (int) $productB->total_quantity === 5;
            });
    }

    public function test_order_status_distribution_only_counts_orders_in_selected_period(): void
    {
        $admin = $this->makeAdmin();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->makeOrder($cashier, 'pending');
        $this->makeOrder($cashier, 'completed');

        $this->makeOrder(
            $cashier,
            'cancelled',
            '2026-11-14 10:00:00'
        );

        $this->actingAs($admin)
            ->get(route('admin.index') . '?range=today')
            ->assertOk()
            ->assertViewHas('orderStatusTotal', 2)
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('orderStatusDistribution', function ($distribution) {
                $pending = $distribution->firstWhere('key', 'pending');
                $completed = $distribution->firstWhere('key', 'completed');

                return $pending !== null
                    && $completed !== null
                    && $pending['count'] === 1
                    && $completed['count'] === 1
                    && $pending['percentage'] === 50.0
                    && $completed['percentage'] === 50.0;
            });
    }

    public function test_seven_day_filter_excludes_records_outside_the_period(): void
    {
        $admin = $this->makeAdmin();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $recentOrder = $this->makeOrder(
            $cashier,
            'completed',
            '2026-11-10 10:00:00'
        );

        $oldOrder = $this->makeOrder(
            $cashier,
            'completed',
            '2026-11-08 10:00:00'
        );

        $this->makePayment(
            $recentOrder,
            $cashier,
            250,
            'completed',
            '2026-11-10 10:00:00'
        );

        $this->makePayment(
            $oldOrder,
            $cashier,
            900,
            'completed',
            '2026-11-08 10:00:00'
        );

        $this->actingAs($admin)
            ->get(route('admin.index') . '?range=7d')
            ->assertOk()
            ->assertViewHas('range', '7d')
            ->assertViewHas('revenue', 250.0)
            ->assertViewHas('totalOrders', 1);
    }

    public function test_invalid_date_range_falls_back_to_thirty_days(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.index') . '?range=invalid')
            ->assertOk()
            ->assertViewHas('range', '30d');
    }
}