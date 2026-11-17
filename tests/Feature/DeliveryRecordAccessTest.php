<?php

namespace Tests\Feature;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryRecordAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(?User $staff = null): DeliveryDetail
    {
        $creator = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(12)),
            'user_id' => $creator->id,
            'order_type' => 'delivery',
            'status' => 'out_for_delivery',
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
            'delivery_instructions' => 'Test instructions',
            'delivery_fee' => 50,
            'status' => $staff ? 'assigned' : 'cancelled',
        ]);
    }

    public function test_delivery_staff_index_shows_only_their_assigned_records(): void
    {
        $staffA = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $staffB = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $ownDelivery = $this->makeDelivery($staffA);
        $otherDelivery = $this->makeDelivery($staffB);

        $response = $this->actingAs($staffA)
            ->get(route('delivery.index'));

        $response->assertOk();
        $response->assertSee($ownDelivery->order->order_number);
        $response->assertDontSee($otherDelivery->order->order_number);
    }

    public function test_admin_index_shows_all_assigned_and_unassigned_records(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $assigned = $this->makeDelivery($staff);
        $unassigned = $this->makeDelivery();

        $response = $this->actingAs($admin)
            ->get(route('delivery.index'));

        $response->assertOk();
        $response->assertSee($assigned->order->order_number);
        $response->assertSee($unassigned->order->order_number);
    }

    public function test_delivery_staff_can_view_their_assigned_record(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff);

        $this->actingAs($staff)
            ->get(route('delivery.show', $delivery))
            ->assertOk()
            ->assertSee('Test Delivery Address');
    }

    public function test_delivery_staff_cannot_view_another_staff_record(): void
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

        $this->actingAs($staffA)
            ->get(route('delivery.show', $delivery))
            ->assertForbidden();
    }

    public function test_delivery_staff_cannot_view_unassigned_record(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($staff)
            ->get(route('delivery.show', $delivery))
            ->assertForbidden();
    }

    public function test_admin_can_view_unassigned_record(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery();

        $this->actingAs($admin)
            ->get(route('delivery.show', $delivery))
            ->assertOk()
            ->assertSee('Test Delivery Address');
    }

    public function test_guest_cannot_access_delivery_listing_or_details(): void
    {
        $staff = User::factory()->create([
            'role' => 'delivery_staff',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery($staff);

        $this->get(route('delivery.index'))
            ->assertRedirect('/login');

        $this->get(route('delivery.show', $delivery))
            ->assertRedirect('/login');
    }
}