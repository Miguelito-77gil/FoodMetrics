<?php

namespace Tests\Feature;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAssignmentPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(
        string $status = 'pending',
        ?User $staff = null,
        ?string $orderNumber = null
    ): DeliveryDetail {
        $creator = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => $orderNumber
                ?? 'ORD-' . strtoupper(Str::random(12)),
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
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Test Delivery Address',
            'delivery_fee' => 50,
            'status' => $status,
        ]);
    }

    public function test_admin_can_view_delivery_assignment_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.deliveries.index'));

        $response->assertOk();
        $response->assertViewIs('admin.deliveries.index');
        $response->assertSee('Delivery Assignments');
        $response->assertSee('Pending and Unassigned Deliveries');
    }

    public function test_page_only_displays_pending_unassigned_deliveries_and_active_staff(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $activeStaff = User::factory()->create([
            'name' => 'Active Test Driver',
            'email' => 'active-driver@test.com',
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $inactiveStaff = User::factory()->create([
            'name' => 'Inactive Test Driver',
            'email' => 'inactive-driver@test.com',
            'role' => 'delivery_staff',
            'is_active' => false,
        ]);

        $pendingDelivery = $this->makeDelivery(
            'pending',
            null,
            'ORD-PENDING-001'
        );

        $cancelledDelivery = $this->makeDelivery(
            'cancelled',
            null,
            'ORD-CANCELLED-001'
        );

        $assignedDelivery = $this->makeDelivery(
            'assigned',
            $activeStaff,
            'ORD-ASSIGNED-001'
        );

        $deliveredDelivery = $this->makeDelivery(
            'delivered',
            $activeStaff,
            'ORD-DELIVERED-001'
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.deliveries.index'));

        $response->assertOk();

        $response->assertSee('ORD-PENDING-001');
        $response->assertSee('Test Recipient');
        $response->assertSee('Test Delivery Address');

        $response->assertDontSee('ORD-CANCELLED-001');
        $response->assertDontSee('ORD-ASSIGNED-001');
        $response->assertDontSee('ORD-DELIVERED-001');

        $response->assertSee('Active Test Driver');
        $response->assertSee('active-driver@test.com');

        $response->assertDontSee('Inactive Test Driver');
        $response->assertDontSee('inactive-driver@test.com');
    }

    public function test_delivery_staff_cannot_view_admin_assignment_page(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->get(route('admin.deliveries.index'));

        $response->assertForbidden();
    }

    public function test_cashier_cannot_view_admin_assignment_page(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($cashier)
            ->get(route('admin.deliveries.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_assignment_page(): void
    {
        $response = $this->get(route('admin.deliveries.index'));

        $response->assertRedirect('/login');
    }
}