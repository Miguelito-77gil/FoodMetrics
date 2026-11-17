<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryRecommendation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInventoryRecommendationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 12:00:00');
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

    private function makeCashier(): User
    {
        return User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    private function makeRecommendation(array $overrides = []): InventoryRecommendation
    {
        $ingredient = Ingredient::create([
            'name' => 'Review Test Ingredient ' . Str::random(8),
            'base_unit' => 'kg',
            'current_stock' => 2.000,
            'minimum_stock' => 3.000,
            'cost_per_base_unit' => 10.0000,
            'is_active' => true,
        ]);

        return InventoryRecommendation::create(array_merge([
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => null,
            'current_stock' => 2.000,
            'recommended_quantity' => 5.000,
            'estimated_cost' => 50.00,
            'recommended_order_date' => '2026-11-16',
            'expected_stockout_date' => '2026-11-20',
            'priority' => 'high',
            'status' => 'pending',
            'reason' => 'Test recommendation requiring admin review.',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ], $overrides));
    }

    public function test_admin_can_approve_pending_recommendation_without_changing_stock(): void
    {
        $admin = $this->makeAdmin();
        $recommendation = $this->makeRecommendation();
        $originalStock = $recommendation->ingredient->current_stock;

        $response = $this->actingAs($admin)->post(
            route('admin.inventory-recommendations.approve', $recommendation)
        );

        $response->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $recommendation->refresh();

        $this->assertSame('approved', $recommendation->status);
        $this->assertSame($admin->id, $recommendation->reviewed_by);
        $this->assertNotNull($recommendation->reviewed_at);
        $this->assertSame(
            $originalStock,
            $recommendation->ingredient->fresh()->current_stock
        );

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_can_dismiss_pending_recommendation(): void
    {
        $admin = $this->makeAdmin();
        $recommendation = $this->makeRecommendation();

        $response = $this->actingAs($admin)->post(
            route('admin.inventory-recommendations.dismiss', $recommendation)
        );

        $response->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $recommendation->refresh();

        $this->assertSame('dismissed', $recommendation->status);
        $this->assertSame($admin->id, $recommendation->reviewed_by);
        $this->assertNotNull($recommendation->reviewed_at);
    }

    public function test_admin_cannot_approve_zero_quantity_recommendation(): void
    {
        $admin = $this->makeAdmin();
        $recommendation = $this->makeRecommendation([
            'recommended_quantity' => 0,
            'estimated_cost' => 0,
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.inventory-recommendations.approve', $recommendation)
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('inventory_recommendations', [
            'id' => $recommendation->id,
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function test_cashier_cannot_review_inventory_recommendation(): void
    {
        $cashier = $this->makeCashier();
        $recommendation = $this->makeRecommendation();

        $response = $this->actingAs($cashier)->post(
            route('admin.inventory-recommendations.approve', $recommendation)
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('inventory_recommendations', [
            'id' => $recommendation->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_already_reviewed_recommendation_cannot_be_reviewed_again(): void
    {
        $admin = $this->makeAdmin();
        $recommendation = $this->makeRecommendation([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.inventory-recommendations.dismiss', $recommendation)
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('inventory_recommendations', [
            'id' => $recommendation->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_dashboard_receives_pending_inventory_recommendations(): void
    {
        $admin = $this->makeAdmin();
        $pending = $this->makeRecommendation();

        $this->makeRecommendation([
            'status' => 'dismissed',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.index'));

        $response->assertOk()
            ->assertViewHas('pendingInventoryRecommendations', function ($records) use ($pending) {
                return $records->contains('id', $pending->id)
                    && $records->every(fn ($record) => $record->status === 'pending');
            })
            ->assertViewHas('pendingInventoryRecommendationCount', 1);
    }
}