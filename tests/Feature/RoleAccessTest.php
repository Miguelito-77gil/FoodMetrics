<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createRoleUser(string $role): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' Test',
            'email' => $role . '@foodmetrics.test',
            'password' => Hash::make('TestPassword123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function registerProtectedRoute(
        string $uri,
        string $allowedRoles
    ): void {
        Route::middleware(['auth', 'active', 'role:' . $allowedRoles])
            ->get($uri, function () {
                return response('AUTHORIZED', 200);
            });
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $this->registerProtectedRoute('/test/admin-only', 'admin');

        $this->actingAs($this->createRoleUser('admin'))
            ->get('/test/admin-only')
            ->assertOk()
            ->assertSee('AUTHORIZED');
    }

    public function test_cashier_cannot_access_admin_only_route(): void
    {
        $this->registerProtectedRoute('/test/admin-only', 'admin');

        $this->actingAs($this->createRoleUser('cashier'))
            ->get('/test/admin-only')
            ->assertForbidden();
    }

    public function test_inventory_staff_can_access_inventory_route(): void
    {
        $this->registerProtectedRoute(
            '/test/inventory',
            'admin,inventory_staff'
        );

        $this->actingAs($this->createRoleUser('inventory_staff'))
            ->get('/test/inventory')
            ->assertOk()
            ->assertSee('AUTHORIZED');
    }

    public function test_inventory_staff_cannot_access_delivery_route(): void
    {
        $this->registerProtectedRoute(
            '/test/delivery',
            'admin,delivery_staff'
        );

        $this->actingAs($this->createRoleUser('inventory_staff'))
            ->get('/test/delivery')
            ->assertForbidden();
    }

    public function test_delivery_staff_can_access_delivery_route(): void
    {
        $this->registerProtectedRoute(
            '/test/delivery',
            'admin,delivery_staff'
        );

        $this->actingAs($this->createRoleUser('delivery_staff'))
            ->get('/test/delivery')
            ->assertOk()
            ->assertSee('AUTHORIZED');
    }

    public function test_delivery_staff_cannot_access_inventory_route(): void
    {
        $this->registerProtectedRoute(
            '/test/inventory',
            'admin,inventory_staff'
        );

        $this->actingAs($this->createRoleUser('delivery_staff'))
            ->get('/test/inventory')
            ->assertForbidden();
    }

    public function test_cashier_can_access_multi_role_route(): void
    {
        $this->registerProtectedRoute(
            '/test/cashier-orders',
            'admin,cashier'
        );

        $this->actingAs($this->createRoleUser('cashier'))
            ->get('/test/cashier-orders')
            ->assertOk()
            ->assertSee('AUTHORIZED');
    }
}