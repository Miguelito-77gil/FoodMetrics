<?php

namespace Tests\Feature;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDeliveryMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(
        string $status = 'pending',
        ?User $staff = null
    ): DeliveryDetail {
        $creator = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $creator->id,
            'order_type' => 'delivery',
            'status' => 'ready',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 50,
            'total_amount' => 150,
            'ordered_at' => now(),
        ]);

        return DeliveryDetail::create([
            'order_id' => $order->id,
            'delivery_staff_id' => $staff?->id,
            'recipient_name' => 'Monitoring Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Monitoring Test Address',
            'delivery_fee' => 50,
            'status' => $status,
        ]);
    }

    public function test_admin_dashboard_displays_actual_delivery_status_counts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->makeDelivery('pending');
        $this->makeDelivery('assigned', $staff);
        $this->makeDelivery('picked_up', $staff);
        $this->makeDelivery('delivered', $staff);
        $this->makeDelivery('failed', $staff);
        $this->makeDelivery('cancelled');

        $response = $this->actingAs($admin)
            ->get(route('admin.index'));

        $response->assertOk();
        $response->assertViewIs('admin.dashboard');

        $response->assertViewHas('totalDeliveries', 6);

        $response->assertViewHas('deliveryStats', function (array $stats) {
            return $stats['pending'] === 1
                && $stats['assigned'] === 1
                && $stats['picked_up'] === 1
                && $stats['delivered'] === 1
                && $stats['failed'] === 1
                && $stats['cancelled'] === 1;
        });

        $response->assertSee('Delivery Status Distribution');
        $response->assertSee('Total Deliveries');
    }

    public function test_dashboard_calculates_delivery_staff_workload_correctly(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'name' => 'Workload Test Driver',
            'email' => 'workload-driver@test.com',
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $this->makeDelivery('assigned', $staff);
        $this->makeDelivery('assigned', $staff);
        $this->makeDelivery('picked_up', $staff);
        $this->makeDelivery('delivered', $staff);
        $this->makeDelivery('failed', $staff);

        $response = $this->actingAs($admin)
            ->get(route('admin.index'));

        $response->assertOk();

        $response->assertViewHas('deliveryStaff', function ($deliveryStaff) use ($staff) {
            $driver = $deliveryStaff->firstWhere('id', $staff->id);

            if (!$driver) {
                return false;
            }

            return $driver->active_deliveries === 3
                && $driver->delivery_counts['assigned'] === 2
                && $driver->delivery_counts['picked_up'] === 1
                && $driver->delivery_counts['delivered'] === 1
                && $driver->delivery_counts['failed'] === 1;
        });

        $response->assertSee('Workload Test Driver');
        $response->assertSee('Active deliveries:');
    }

    public function test_inactive_delivery_staff_are_excluded_from_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $inactiveStaff = User::factory()->create([
            'name' => 'Inactive Monitoring Driver',
            'email' => 'inactive-monitor@test.com',
            'role' => 'delivery_staff',
            'is_active' => false,
        ]);

        $this->makeDelivery('delivered', $inactiveStaff);

        $response = $this->actingAs($admin)
            ->get(route('admin.index'));

        $response->assertOk();

        $response->assertViewHas('deliveryStaff', function ($deliveryStaff) use ($inactiveStaff) {
            return $deliveryStaff->doesntContain(
                fn ($staff) => $staff->id === $inactiveStaff->id
            );
        });

        $response->assertDontSee('Inactive Monitoring Driver');
        $response->assertDontSee('inactive-monitor@test.com');
    }

    public function test_cashier_cannot_access_admin_monitoring_dashboard(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($cashier)
            ->get(route('admin.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        $response = $this->get(route('admin.index'));

        $response->assertRedirect('/login');
    }
}