<?php

namespace Tests\Feature;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAssignmentTest extends TestCase
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
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Test Delivery Address',
            'delivery_fee' => 50,
            'status' => $status,
        ]);
    }

    public function test_admin_can_assign_pending_delivery_to_active_delivery_staff(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $response = $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            [
                'delivery_staff_id' => $staff->id,
                'remarks' => 'Assigned to available delivery staff.',
            ]
        );

        $response->assertRedirect(route('delivery.show', $delivery));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => $staff->id,
            'status' => 'assigned',
        ]);

        $this->assertNotNull($delivery->fresh()->assigned_at);
    }

    public function test_assignment_creates_delivery_status_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            [
                'delivery_staff_id' => $staff->id,
                'remarks' => 'New delivery assignment.',
            ]
        )->assertRedirect(route('delivery.show', $delivery));

        $this->assertDatabaseHas('delivery_status_history', [
            'delivery_detail_id' => $delivery->id,
            'updated_by' => $admin->id,
            'previous_status' => 'pending',
            'new_status' => 'assigned',
            'remarks' => 'New delivery assignment.',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 1);
    }

    public function test_inactive_delivery_staff_cannot_be_assigned(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $inactiveStaff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => false,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $inactiveStaff->id]
        )->assertSessionHasErrors('delivery_staff_id');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => null,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_non_delivery_staff_account_cannot_be_assigned(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $cashier->id]
        )->assertSessionHasErrors('delivery_staff_id');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_delivery_staff_cannot_access_admin_assignment_endpoint(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $anotherStaff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($staff)->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $anotherStaff->id]
        )->assertForbidden();

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => null,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_cancelled_delivery_cannot_be_assigned(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery('cancelled');

        $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $staff->id]
        )->assertStatus(422);

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => null,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_already_assigned_delivery_cannot_be_reassigned(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staffA = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $staffB = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery('assigned', $staffA);

        $this->actingAs($admin)->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $staffB->id]
        )->assertStatus(422);

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => $staffA->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseCount('delivery_status_history', 0);
    }

    public function test_guest_cannot_assign_delivery(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->post(
            route('admin.deliveries.assign', $delivery),
            ['delivery_staff_id' => $staff->id]
        )->assertRedirect('/login');

        $this->assertDatabaseHas('delivery_details', [
            'id' => $delivery->id,
            'delivery_staff_id' => null,
            'status' => 'pending',
        ]);
    }
}