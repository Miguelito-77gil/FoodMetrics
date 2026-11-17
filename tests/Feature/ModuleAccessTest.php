<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' Test',
            'email' => $role . '@foodmetrics.test',
            'password' => Hash::make('TestPassword123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_module_routes(): void
    {
        foreach (['admin', 'cashier', 'inventory', 'delivery'] as $module) {
            $this->get('/' . $module)->assertRedirect('/login');
        }
    }

    public function test_admin_can_access_all_module_routes(): void
    {
        $this->actingAs($this->createUserWithRole('admin'));

        $this->get('/admin')->assertOk();

        $this->get('/cashier')
            ->assertRedirect(route('orders.index'));

        $this->get('/inventory')->assertOk();
        $this->get('/delivery')->assertOk();
    }

    public function test_cashier_has_only_cashier_module_access(): void
    {
        $this->actingAs($this->createUserWithRole('cashier'));

        $this->get('/admin')->assertForbidden();

        $this->get('/cashier')
            ->assertRedirect(route('orders.index'));

        $this->get('/inventory')->assertForbidden();
        $this->get('/delivery')->assertForbidden();
    }

    public function test_inventory_staff_has_only_inventory_module_access(): void
    {
        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $this->get('/admin')->assertForbidden();
        $this->get('/cashier')->assertForbidden();
        $this->get('/inventory')->assertOk();
        $this->get('/delivery')->assertForbidden();
    }

    public function test_delivery_staff_has_only_delivery_module_access(): void
    {
        $this->actingAs($this->createUserWithRole('delivery_staff'));

        $this->get('/admin')->assertForbidden();
        $this->get('/cashier')->assertForbidden();
        $this->get('/inventory')->assertForbidden();
        $this->get('/delivery')->assertOk();
    }
}