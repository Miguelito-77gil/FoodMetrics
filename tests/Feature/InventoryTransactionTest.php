<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role = 'inventory_staff'): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' Test',
            'email' => $role . '@transactions.test',
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
            'notes' => 'Transaction test ingredient',
            'is_active' => true,
        ], $overrides));
    }

    public function test_stock_in_updates_stock_and_weighted_average_cost(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        $movement = app(InventoryTransactionService::class)->stockIn(
            $ingredient->id,
            $user->id,
            2.500,
            200.0000,
            'Supplier delivery'
        );

        $ingredient->refresh();

        $this->assertSame('15.000', $ingredient->current_stock);
        $this->assertSame('158.3333', $ingredient->cost_per_base_unit);

        $this->assertSame('stock_in', $movement->movement_type);
        $this->assertSame('2.500', $movement->quantity);
        $this->assertSame('12.500', $movement->stock_before);
        $this->assertSame('15.000', $movement->stock_after);
        $this->assertSame('200.0000', $movement->unit_cost);
        $this->assertSame('500.00', $movement->total_cost);
        $this->assertSame($user->id, $movement->user_id);
        $this->assertSame('Supplier delivery', $movement->reason);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_stock_in_from_zero_stock_uses_incoming_purchase_cost(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient([
            'current_stock' => 0,
            'cost_per_base_unit' => 150,
        ]);

        app(InventoryTransactionService::class)->stockIn(
            $ingredient->id,
            $user->id,
            5,
            85.5000,
            'Initial stock receipt'
        );

        $ingredient->refresh();

        $this->assertSame('5.000', $ingredient->current_stock);
        $this->assertSame('85.5000', $ingredient->cost_per_base_unit);
    }

    public function test_adjustment_increase_and_decrease_preserve_average_cost(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();
        $service = app(InventoryTransactionService::class);

        $increase = $service->adjust(
            $ingredient->id,
            $user->id,
            'increase',
            2.500,
            'Physical count correction'
        );

        $ingredient->refresh();

        $this->assertSame('15.000', $ingredient->current_stock);
        $this->assertSame('150.0000', $ingredient->cost_per_base_unit);
        $this->assertSame('adjustment', $increase->movement_type);
        $this->assertSame('12.500', $increase->stock_before);
        $this->assertSame('15.000', $increase->stock_after);

        $decrease = $service->adjust(
            $ingredient->id,
            $user->id,
            'decrease',
            5.000,
            'Corrected physical count'
        );

        $ingredient->refresh();

        $this->assertSame('10.000', $ingredient->current_stock);
        $this->assertSame('150.0000', $ingredient->cost_per_base_unit);
        $this->assertSame('15.000', $decrease->stock_before);
        $this->assertSame('10.000', $decrease->stock_after);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_waste_reduces_stock_and_records_its_inventory_value(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        $movement = app(InventoryTransactionService::class)->recordWaste(
            $ingredient->id,
            $user->id,
            2.250,
            'Expired ingredient'
        );

        $ingredient->refresh();

        $this->assertSame('10.250', $ingredient->current_stock);
        $this->assertSame('150.0000', $ingredient->cost_per_base_unit);

        $this->assertSame('waste', $movement->movement_type);
        $this->assertSame('2.250', $movement->quantity);
        $this->assertSame('12.500', $movement->stock_before);
        $this->assertSame('10.250', $movement->stock_after);
        $this->assertSame('150.0000', $movement->unit_cost);
        $this->assertSame('337.50', $movement->total_cost);
        $this->assertSame('Expired ingredient', $movement->reason);
    }

    public function test_adjustment_cannot_reduce_stock_below_zero(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        try {
            app(InventoryTransactionService::class)->adjust(
                $ingredient->id,
                $user->id,
                'decrease',
                20,
                'Invalid stock decrease'
            );

            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $ingredient->refresh();

        $this->assertSame('12.500', $ingredient->current_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_waste_cannot_reduce_stock_below_zero(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        try {
            app(InventoryTransactionService::class)->recordWaste(
                $ingredient->id,
                $user->id,
                13,
                'Excess waste quantity'
            );

            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $ingredient->refresh();

        $this->assertSame('12.500', $ingredient->current_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_inactive_ingredient_rejects_manual_transactions(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient([
            'is_active' => false,
        ]);

        try {
            app(InventoryTransactionService::class)->stockIn(
                $ingredient->id,
                $user->id,
                2,
                100,
                'Inactive ingredient receipt'
            );

            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ingredient_id', $exception->errors());
        }

        $ingredient->refresh();

        $this->assertSame('12.500', $ingredient->current_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_inventory_staff_can_open_transaction_forms_and_history(): void
    {
        $this->actingAs($this->createUserWithRole());

        $this->get(route('inventory.transactions.stock-in.create'))
            ->assertOk()
            ->assertSee('Receive Stock');

        $this->get(route('inventory.transactions.adjustment.create'))
            ->assertOk()
            ->assertSee('Adjust Stock');

        $this->get(route('inventory.transactions.waste.create'))
            ->assertOk()
            ->assertSee('Record Ingredient Waste');

        $this->get(route('inventory.movements.index'))
            ->assertOk()
            ->assertSee('Movement History');
    }

    public function test_cashier_cannot_access_inventory_transactions(): void
    {
        $this->actingAs($this->createUserWithRole('cashier'));

        $this->get(route('inventory.transactions.stock-in.create'))
            ->assertForbidden();

        $this->get(route('inventory.movements.index'))
            ->assertForbidden();
    }

    public function test_stock_in_form_submission_records_transaction(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        $this->actingAs($user);

        $this->post(route('inventory.transactions.stock-in.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 2.500,
            'unit_cost' => 200.0000,
            'reason' => 'Controller stock receipt',
        ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $ingredient->refresh();

        $this->assertSame('15.000', $ingredient->current_stock);
        $this->assertSame('158.3333', $ingredient->cost_per_base_unit);

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $ingredient->id,
            'user_id' => $user->id,
            'movement_type' => 'stock_in',
            'reason' => 'Controller stock receipt',
        ]);
    }

    public function test_movement_search_and_type_filters_are_grouped_correctly(): void
    {
        $user = $this->createUserWithRole();
        $ingredient = $this->createIngredient();

        $service = app(InventoryTransactionService::class);

        $service->stockIn(
            $ingredient->id,
            $user->id,
            2,
            100,
            'Supplier purchase delivery'
        );

        $service->recordWaste(
            $ingredient->id,
            $user->id,
            1,
            'Damaged ingredient'
        );

        $this->actingAs($user);

        $this->get(route('inventory.movements.index', [
            'search' => 'purchase',
            'type' => 'waste',
        ]))
            ->assertOk()
            ->assertSee('No inventory movements found')
            ->assertDontSee('Supplier purchase delivery');
    }
}