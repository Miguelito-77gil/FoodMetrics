<?php

namespace Tests\Feature;

use App\Models\ForecastRun;
use App\Models\Ingredient;
use App\Models\InventoryRecommendation;
use App\Models\User;
use App\Services\LowStockRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeIngredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Test Ingredient',
            'base_unit' => 'kg',
            'current_stock' => 5,
            'minimum_stock' => 5,
            'cost_per_base_unit' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function makeForecastRun(): ForecastRun
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        return ForecastRun::create([
            'forecast_name' => 'Test Inventory Forecast',
            'forecast_type' => 'inventory',
            'model_name' => 'Test Model',
            'model_version' => '1.0',
            'forecast_start_date' => now()->addDay()->toDateString(),
            'forecast_end_date' => now()->addDays(7)->toDateString(),
            'status' => 'completed',
            'created_by' => $user->id,
        ]);
    }

    public function test_it_creates_recommendation_when_stock_equals_minimum(): void
    {
        $ingredient = $this->makeIngredient();

        $recommendation = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertNotNull($recommendation);
        $this->assertNull($recommendation->forecast_run_id);
        $this->assertEquals(5.000, (float) $recommendation->recommended_quantity);
        $this->assertEquals(50.00, (float) $recommendation->estimated_cost);
        $this->assertEquals('pending', $recommendation->status);

        $this->assertDatabaseCount('inventory_recommendations', 1);
    }

    public function test_it_uses_full_minimum_stock_as_recommended_quantity(): void
    {
        $ingredient = $this->makeIngredient([
            'current_stock' => 2,
            'minimum_stock' => 5,
        ]);

        $recommendation = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertEquals(5.000, (float) $recommendation->recommended_quantity);
        $this->assertEquals(50.00, (float) $recommendation->estimated_cost);
    }

    public function test_it_updates_existing_pending_automatic_recommendation(): void
    {
        $ingredient = $this->makeIngredient([
            'current_stock' => 4,
            'minimum_stock' => 5,
        ]);

        $existing = InventoryRecommendation::create([
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => null,
            'current_stock' => 4,
            'recommended_quantity' => 5,
            'estimated_cost' => 50,
            'priority' => 'medium',
            'status' => 'pending',
            'reason' => 'Previous automatic recommendation.',
        ]);

        $ingredient->update(['current_stock' => 3]);

        $updated = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient->fresh());

        $this->assertSame($existing->id, $updated->id);
        $this->assertEquals(3.000, (float) $updated->current_stock);
        $this->assertEquals(5.000, (float) $updated->recommended_quantity);
        $this->assertDatabaseCount('inventory_recommendations', 1);
    }

    public function test_it_does_not_duplicate_pending_forecast_recommendation(): void
    {
        $ingredient = $this->makeIngredient();
        $run = $this->makeForecastRun();

        $forecastRecommendation = InventoryRecommendation::create([
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => $run->id,
            'current_stock' => 5,
            'recommended_quantity' => 9,
            'estimated_cost' => 90,
            'priority' => 'high',
            'status' => 'pending',
            'reason' => 'Forecast-generated recommendation.',
        ]);

        $result = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertNull($result);
        $this->assertDatabaseCount('inventory_recommendations', 1);

        $this->assertDatabaseHas('inventory_recommendations', [
            'id' => $forecastRecommendation->id,
            'forecast_run_id' => $run->id,
            'recommended_quantity' => 9,
            'reason' => 'Forecast-generated recommendation.',
        ]);
    }

    public function test_it_does_not_update_reviewed_automatic_recommendation(): void
    {
        $ingredient = $this->makeIngredient([
            'current_stock' => 4,
            'minimum_stock' => 5,
        ]);

        $reviewer = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $existing = InventoryRecommendation::create([
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => null,
            'current_stock' => 4,
            'recommended_quantity' => 5,
            'estimated_cost' => 50,
            'priority' => 'medium',
            'status' => 'approved',
            'reason' => 'Reviewed recommendation.',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $result = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertNotNull($result);
        $this->assertNotSame($existing->id, $result->id);
        $this->assertDatabaseCount('inventory_recommendations', 2);

        $this->assertDatabaseHas('inventory_recommendations', [
            'id' => $existing->id,
            'status' => 'approved',
            'reason' => 'Reviewed recommendation.',
        ]);
    }

    public function test_it_does_not_create_recommendation_when_stock_is_above_minimum(): void
    {
        $ingredient = $this->makeIngredient([
            'current_stock' => 6,
            'minimum_stock' => 5,
        ]);

        $result = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertNull($result);
        $this->assertDatabaseCount('inventory_recommendations', 0);
    }

    public function test_it_does_not_create_recommendation_for_inactive_ingredient(): void
    {
        $ingredient = $this->makeIngredient([
            'current_stock' => 2,
            'minimum_stock' => 5,
            'is_active' => false,
        ]);

        $result = app(LowStockRecommendationService::class)
            ->syncForIngredient($ingredient);

        $this->assertNull($result);
        $this->assertDatabaseCount('inventory_recommendations', 0);
    }
}
