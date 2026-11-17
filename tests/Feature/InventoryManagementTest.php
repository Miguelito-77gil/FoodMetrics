<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' Test',
            'email' => $role . '@inventory.test',
            'password' => Hash::make('TestPassword123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createIngredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Test Ingredient',
            'base_unit' => 'kg',
            'current_stock' => 12.500,
            'minimum_stock' => 3.000,
            'cost_per_base_unit' => 150.0000,
            'storage_location' => 'Storage A',
            'notes' => 'Test inventory record',
            'is_active' => true,
        ], $overrides));
    }

    public function test_guest_cannot_access_inventory_pages(): void
    {
        $ingredient = $this->createIngredient();

        $this->get(route('inventory.index'))
            ->assertRedirect('/login');

        $this->get(route('inventory.ingredients.create'))
            ->assertRedirect('/login');

        $this->get(route('inventory.ingredients.edit', $ingredient))
            ->assertRedirect('/login');
    }

    public function test_admin_can_access_inventory_and_ingredient_forms(): void
    {
        $ingredient = $this->createIngredient();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->get(route('inventory.index'))->assertOk();
        $this->get(route('inventory.ingredients.create'))->assertOk();
        $this->get(route('inventory.ingredients.edit', $ingredient))->assertOk();
    }

    public function test_inventory_staff_can_access_inventory_and_ingredient_forms(): void
    {
        $ingredient = $this->createIngredient();

        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $this->get(route('inventory.index'))->assertOk();
        $this->get(route('inventory.ingredients.create'))->assertOk();
        $this->get(route('inventory.ingredients.edit', $ingredient))->assertOk();
    }

    public function test_cashier_and_delivery_staff_cannot_access_inventory(): void
    {
        foreach (['cashier', 'delivery_staff'] as $role) {
            $this->actingAs($this->createUserWithRole($role));

            $this->get(route('inventory.index'))->assertForbidden();

            $this->get(route('inventory.ingredients.create'))
                ->assertForbidden();

            auth()->logout();
        }
    }

    public function test_new_ingredient_is_created_with_zero_initial_stock(): void
    {
        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $response = $this->post(route('inventory.ingredients.store'), [
            'name' => 'New Test Flour',
            'base_unit' => 'kg',
            'minimum_stock' => 5,
            'cost_per_base_unit' => 85.5000,
            'storage_location' => 'Dry Storage',
            'notes' => 'New ingredient test',
            'current_stock' => 999,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ingredients', [
            'name' => 'New Test Flour',
            'base_unit' => 'kg',
            'current_stock' => 0,
            'minimum_stock' => 5,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_ingredient_name_is_rejected(): void
    {
        $this->createIngredient([
            'name' => 'Existing Flour',
        ]);

        $this->actingAs($this->createUserWithRole('admin'));

        $response = $this->post(route('inventory.ingredients.store'), [
            'name' => 'Existing Flour',
            'base_unit' => 'kg',
            'minimum_stock' => 5,
            'cost_per_base_unit' => 85,
        ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('ingredients', 1);
    }

    public function test_ingredient_update_preserves_current_stock_and_status(): void
    {
        $ingredient = $this->createIngredient([
            'name' => 'Original Flour',
            'current_stock' => 18.750,
            'is_active' => true,
        ]);

        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $response = $this->put(
            route('inventory.ingredients.update', $ingredient),
            [
                'name' => 'Updated Flour',
                'base_unit' => 'kg',
                'minimum_stock' => 6,
                'cost_per_base_unit' => 95,
                'storage_location' => 'Storage B',
                'notes' => 'Updated ingredient details',
                'current_stock' => 999,
                'is_active' => false,
            ]
        );

        $response->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'name' => 'Updated Flour',
            'current_stock' => 18.750,
            'is_active' => 1,
            'minimum_stock' => 6,
            'storage_location' => 'Storage B',
        ]);
    }

    public function test_ingredient_status_can_be_toggled_without_changing_stock(): void
    {
        $ingredient = $this->createIngredient([
            'current_stock' => 7.250,
            'is_active' => true,
        ]);

        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $response = $this->patch(
            route('inventory.ingredients.status', $ingredient)
        );

        $response->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'current_stock' => 7.250,
            'is_active' => 0,
        ]);
    }

    public function test_reactivating_ingredient_does_not_change_stock(): void
    {
        $ingredient = $this->createIngredient([
            'current_stock' => 4.125,
            'is_active' => false,
        ]);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('inventory.ingredients.status', $ingredient))
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'current_stock' => 4.125,
            'is_active' => 1,
        ]);
    }

    public function test_inventory_search_filters_ingredients_by_name(): void
    {
        $this->createIngredient(['name' => 'Chicken Breast']);
        $this->createIngredient(['name' => 'Cooking Oil']);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->get(route('inventory.index', ['search' => 'Chicken']))
            ->assertOk()
            ->assertSee('Chicken Breast')
            ->assertDontSee('Cooking Oil');
    }

    public function test_inventory_status_filter_displays_only_inactive_ingredients(): void
    {
        $this->createIngredient([
            'name' => 'Active Ingredient',
            'is_active' => true,
        ]);

        $this->createIngredient([
            'name' => 'Inactive Ingredient',
            'is_active' => false,
        ]);

        $this->actingAs($this->createUserWithRole('inventory_staff'));

        $this->get(route('inventory.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Inactive Ingredient')
            ->assertViewHas('ingredients', function ($ingredients) {
                return $ingredients->total() === 1
                    && $ingredients->getCollection()->pluck('name')->all() === ['Inactive Ingredient'];
            });
    }
}