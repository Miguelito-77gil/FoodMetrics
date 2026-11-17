<?php

namespace Tests\Feature;

use App\Models\DeliveryDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DeliveryOwnershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeDelivery(?int $staffId): DeliveryDetail
    {
        return new DeliveryDetail([
            'delivery_staff_id' => $staffId,
        ]);
    }

    public function test_admin_can_view_all_delivery_records(): void
    {
        $admin = $this->makeUser('admin');

        $ownDelivery = $this->makeDelivery(999);
        $unassignedDelivery = $this->makeDelivery(null);

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', DeliveryDetail::class)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $ownDelivery)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $unassignedDelivery)
        );
    }

    public function test_delivery_staff_can_view_only_assigned_delivery(): void
    {
        $staff = $this->makeUser('delivery_staff');

        $assignedDelivery = $this->makeDelivery($staff->id);
        $otherDelivery = $this->makeDelivery($staff->id + 100);
        $unassignedDelivery = $this->makeDelivery(null);

        $this->assertTrue(
            Gate::forUser($staff)->allows('viewAny', DeliveryDetail::class)
        );

        $this->assertTrue(
            Gate::forUser($staff)->allows('view', $assignedDelivery)
        );

        $this->assertFalse(
            Gate::forUser($staff)->allows('view', $otherDelivery)
        );

        $this->assertFalse(
            Gate::forUser($staff)->allows('view', $unassignedDelivery)
        );
    }

    public function test_delivery_staff_can_update_only_assigned_delivery(): void
    {
        $staff = $this->makeUser('delivery_staff');

        $assignedDelivery = $this->makeDelivery($staff->id);
        $otherDelivery = $this->makeDelivery($staff->id + 100);

        $this->assertTrue(
            Gate::forUser($staff)->allows('update', $assignedDelivery)
        );

        $this->assertFalse(
            Gate::forUser($staff)->allows('update', $otherDelivery)
        );
    }

    public function test_only_admin_can_assign_deliveries(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('delivery_staff');

        $this->assertTrue(
            Gate::forUser($admin)->allows('assign', DeliveryDetail::class)
        );

        $this->assertFalse(
            Gate::forUser($staff)->allows('assign', DeliveryDetail::class)
        );
    }

    public function test_only_admin_can_delete_delivery_records(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('delivery_staff');

        $delivery = $this->makeDelivery($staff->id);

        $this->assertTrue(
            Gate::forUser($admin)->allows('delete', $delivery)
        );

        $this->assertFalse(
            Gate::forUser($staff)->allows('delete', $delivery)
        );
    }

    public function test_cashier_and_inventory_staff_cannot_access_deliveries(): void
    {
        $delivery = $this->makeDelivery(null);

        foreach (['cashier', 'inventory_staff'] as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse(
                Gate::forUser($user)->allows('viewAny', DeliveryDetail::class)
            );

            $this->assertFalse(
                Gate::forUser($user)->allows('view', $delivery)
            );

            $this->assertFalse(
                Gate::forUser($user)->allows('update', $delivery)
            );
        }
    }
}