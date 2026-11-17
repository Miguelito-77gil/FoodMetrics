<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\AddOnIngredient;
use App\Models\ForecastRun;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\InventoryRecommendation;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\InventoryRecommendationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00'));
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

    private function makeIngredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Test Ingredient ' . Str::random(8),
            'base_unit' => 'kg',
            'current_stock' => 3.000,
            'minimum_stock' => 2.000,
            'cost_per_base_unit' => 10.0000,
            'is_active' => true,
        ], $overrides));
    }

    private function attachRecipeUsage(Ingredient $ingredient): void
    {
        $recipe = Recipe::create([
            'product_id' => (function (): int {                 $category = \App\Models\Category::create([                     'name' => 'Forecast Test Category ' . Str::random(6),                     'slug' => 'forecast-test-category-' . Str::lower(Str::random(8)),                     'is_active' => true,                     'sort_order' => 1,                 ]);                  return \App\Models\Product::create([                     'category_id' => $category->id,                     'product_code' => 'TEST-' . strtoupper(Str::random(8)),                     'name' => 'Forecast Test Product',                     'slug' => 'forecast-test-product-' . Str::lower(Str::random(8)),                     'base_price' => 100,                     'is_available' => true,                     'is_featured' => false,                     'preparation_time' => 10,                 ])->id;             })(),
            'product_variant_id' => null,
            'name' => 'Forecast Test Recipe',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.100,
            'unit' => $ingredient->base_unit,
        ]);
    }

    private function attachAddOnUsage(Ingredient $ingredient): void
    {
        $addOn = AddOn::create([
            'name' => 'Forecast Test Add-on ' . Str::random(6),
            'description' => 'Inventory forecast test',
            'price' => 10,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.100,
            'unit' => $ingredient->base_unit,
        ]);
    }

    private function makeMovement(
        Ingredient $ingredient,
        string $date,
        float $quantity,
        string $movementType = 'stock_out',
        ?string $referenceType = 'order'
    ): InventoryMovement {
        return InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'user_id' => null,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'stock_before' => 100,
            'stock_after' => 100 - $quantity,
            'unit_cost' => 10,
            'total_cost' => $quantity * 10,
            'reference_type' => $referenceType,
            'reference_id' => null,
            'reason' => 'Automated forecast test data',
            'movement_at' => $date . ' 12:00:00',
        ]);
    }

    public function test_forecast_uses_only_order_consumption_and_stops_training_at_yesterday(): void
    {
        $admin = $this->makeAdmin();

        $ingredient = $this->makeIngredient([
            'current_stock' => 3.000,
            'minimum_stock' => 2.000,
            'cost_per_base_unit' => 10.0000,
        ]);

        $this->attachRecipeUsage($ingredient);

        // Seven distinct historical consumption days: November 8-14.
        for ($day = 8; $day <= 14; $day++) {
            $this->makeMovement(
                $ingredient,
                sprintf('2026-11-%02d', $day),
                1.000
            );
        }

        // Future consumption must not enter the training data.
        $this->makeMovement($ingredient, '2026-11-16', 100.000);

        // Non-order stock-outs and waste must also be excluded.
        $this->makeMovement(
            $ingredient,
            '2026-11-10',
            100.000,
            'stock_out',
            'manual'
        );

        $this->makeMovement(
            $ingredient,
            '2026-11-10',
            100.000,
            'waste',
            'order'
        );

        $run = app(InventoryRecommendationService::class)->generate(
            '2026-11-20',
            '2026-11-26',
            $admin->id,
            7
        );

        $recommendation = $run->inventoryRecommendations()->firstOrFail();

        $this->assertSame('2026-11-08', $run->training_start_date->toDateString());
        $this->assertSame('2026-11-14', $run->training_end_date->toDateString());

        // 7 consumed / 7 calendar days = 1 kg daily.
        // 7-day projected demand + 2 kg minimum stock = 9 kg target.
        // 9 kg target - 3 kg current stock = 6 kg recommended.
        $this->assertEquals(6.000, (float) $recommendation->recommended_quantity);
        $this->assertEquals(60.00, (float) $recommendation->estimated_cost);

        $this->assertSame('2026-11-23', $recommendation->expected_stockout_date->toDateString());
        $this->assertSame('pending', $recommendation->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame(
            'synthetic_simulated_historical_data',
            $run->results_summary['data_label']
        );
    }

    public function test_available_add_on_mapping_counts_as_valid_menu_usage(): void
    {
        $admin = $this->makeAdmin();

        $ingredient = $this->makeIngredient([
            'current_stock' => 0,
            'minimum_stock' => 1,
        ]);

        $this->attachAddOnUsage($ingredient);

        for ($day = 8; $day <= 14; $day++) {
            $this->makeMovement(
                $ingredient,
                sprintf('2026-11-%02d', $day),
                2.000
            );
        }

        $run = app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2026-11-22',
            $admin->id,
            7
        );

        $recommendation = $run->inventoryRecommendations()->firstOrFail();

        // 2 kg/day x 7 days + 1 kg minimum stock = 15 kg.
        $this->assertEquals(15.000, (float) $recommendation->recommended_quantity);
        $this->assertStringNotContainsString(
            'Manual review required',
            $recommendation->reason
        );
        $this->assertSame(0, $run->results_summary['manual_review_required']);
    }

    public function test_ingredient_without_recipe_or_add_on_usage_requires_manual_review(): void
    {
        $admin = $this->makeAdmin();

        $ingredient = $this->makeIngredient([
            'current_stock' => 0,
            'minimum_stock' => 2,
        ]);

        for ($day = 8; $day <= 14; $day++) {
            $this->makeMovement(
                $ingredient,
                sprintf('2026-11-%02d', $day),
                5.000
            );
        }

        $run = app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2026-11-22',
            $admin->id,
            7
        );

        $recommendation = $run->inventoryRecommendations()->firstOrFail();

        // No demand forecast is trusted without a menu mapping.
        // Only the minimum-stock shortfall is suggested.
        $this->assertEquals(2.000, (float) $recommendation->recommended_quantity);
        $this->assertStringContainsString(
            'Manual review required',
            $recommendation->reason
        );
        $this->assertSame(1, $run->results_summary['manual_review_required']);
    }

    public function test_insufficient_distinct_consumption_days_requires_manual_review(): void
    {
        $admin = $this->makeAdmin();

        $ingredient = $this->makeIngredient();
        $this->attachRecipeUsage($ingredient);

        // Six distinct days is below the seven-day minimum.
        for ($day = 9; $day <= 14; $day++) {
            $this->makeMovement(
                $ingredient,
                sprintf('2026-11-%02d', $day),
                1.000
            );
        }

        $run = app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2026-11-22',
            $admin->id,
            7
        );

        $recommendation = $run->inventoryRecommendations()->firstOrFail();

        $this->assertStringContainsString(
            'Manual review required',
            $recommendation->reason
        );
        $this->assertSame(1, $run->results_summary['manual_review_required']);
    }

    public function test_generation_preserves_existing_recommendations_and_creates_a_new_run(): void
    {
        $admin = $this->makeAdmin();

        $ingredient = $this->makeIngredient([
            'current_stock' => 0,
            'minimum_stock' => 2,
        ]);

        $existingRun = ForecastRun::create([
            'forecast_name' => 'Existing Inventory Forecast',
            'forecast_type' => 'inventory',
            'model_name' => 'Existing Model',
            'model_version' => '1.0',
            'forecast_start_date' => '2026-11-16',
            'forecast_end_date' => '2026-11-22',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);

        $existingRecommendation = InventoryRecommendation::create([
            'ingredient_id' => $ingredient->id,
            'forecast_run_id' => $existingRun->id,
            'current_stock' => 5,
            'recommended_quantity' => 9,
            'estimated_cost' => 90,
            'priority' => 'high',
            'status' => 'pending',
            'reason' => 'Existing recommendation must remain unchanged.',
        ]);

        $newRun = app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2026-11-22',
            $admin->id,
            7
        );

        $this->assertNotSame($existingRun->id, $newRun->id);
        $this->assertSame(2, ForecastRun::count());
        $this->assertSame(2, InventoryRecommendation::count());

        $this->assertEquals(
            9.000,
            (float) $existingRecommendation->fresh()->recommended_quantity
        );

        $this->assertSame(
            'Existing recommendation must remain unchanged.',
            $existingRecommendation->fresh()->reason
        );
    }

    public function test_invalid_lookback_period_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->expectException(ValidationException::class);

        app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2026-11-22',
            $admin->id,
            6
        );
    }

    public function test_forecast_horizon_over_ninety_days_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->expectException(ValidationException::class);

        app(InventoryRecommendationService::class)->generate(
            '2026-11-16',
            '2027-02-14',
            $admin->id,
            30
        );
    }

    public function test_forecast_start_date_must_be_in_the_future(): void
    {
        $admin = $this->makeAdmin();
        $this->expectException(ValidationException::class);

        app(InventoryRecommendationService::class)->generate(
            '2026-11-15',
            '2026-11-22',
            $admin->id,
            30
        );
    }
}
