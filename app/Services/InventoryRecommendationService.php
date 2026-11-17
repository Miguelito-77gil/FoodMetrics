<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Models\Ingredient;
use App\Models\InventoryRecommendation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRecommendationService
{
    private const MINIMUM_CONSUMPTION_DAYS = 7;

    public function generate(
        string $forecastStartDate,
        string $forecastEndDate,
        int $createdBy,
        int $lookbackDays = 90
    ): ForecastRun {
        $start = CarbonImmutable::parse($forecastStartDate)->startOfDay();
        $end = CarbonImmutable::parse($forecastEndDate)->startOfDay();

        if ($start->lessThanOrEqualTo(CarbonImmutable::today())) {
            throw ValidationException::withMessages([
                'forecast_start_date' => 'The forecast start date must be in the future.',
            ]);
        }

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'forecast_end_date' => 'The forecast end date must be on or after the start date.',
            ]);
        }

        if ($lookbackDays < 7 || $lookbackDays > 730) {
            throw ValidationException::withMessages([
                'lookback_days' => 'Historical lookback must be between 7 and 730 days.',
            ]);
        }

        $forecastDays = $start->diffInDays($end) + 1;

        if ($forecastDays > 90) {
            throw ValidationException::withMessages([
                'forecast_end_date' => 'The inventory forecast cannot exceed 90 days.',
            ]);
        }

        // Historical data must stop yesterday, even when the forecast
        // starts several days in the future.
        $trainingEnd = CarbonImmutable::yesterday()->endOfDay();
        $trainingStart = $trainingEnd
            ->startOfDay()
            ->subDays($lookbackDays - 1);

        return DB::transaction(function () use (
            $start,
            $end,
            $trainingStart,
            $trainingEnd,
            $forecastDays,
            $lookbackDays,
            $createdBy
        ) {
            $now = now();

            $run = ForecastRun::create([
                'forecast_name' => 'Inventory Replenishment Forecast',
                'forecast_type' => 'inventory',
                'model_name' => 'Historical Average Consumption Baseline',
                'model_version' => '1.0',
                'training_start_date' => $trainingStart->toDateString(),
                'training_end_date' => $trainingEnd->toDateString(),
                'forecast_start_date' => $start->toDateString(),
                'forecast_end_date' => $end->toDateString(),
                'status' => 'running',
                'parameters' => [
                    'lookback_days' => $lookbackDays,
                    'forecast_horizon_days' => $forecastDays,
                    'minimum_consumption_days' => self::MINIMUM_CONSUMPTION_DAYS,
                    'demand_source' => 'order_stock_out_movements',
                    'average_method' => 'total_order_consumption_divided_by_calendar_days',
                    'target_stock_formula' => 'projected_demand_plus_minimum_stock',
                    'recommended_quantity_formula' => 'max(0,target_stock_minus_current_stock)',
                    'rounding' => 'rounded_up_to_0.001_base_unit',
                ],
                'results_summary' => null,
                'error_message' => null,
                'created_by' => $createdBy,
                'started_at' => $now,
                'completed_at' => null,
            ]);

            $consumption = DB::table('inventory_movements')
                ->where('movement_type', 'stock_out')
                ->where('reference_type', 'order')
                ->whereBetween('movement_at', [
                    $trainingStart,
                    $trainingEnd,
                ])
                ->selectRaw(
                    'ingredient_id, SUM(quantity) AS total_consumed, COUNT(DISTINCT DATE(movement_at)) AS consumption_days'
                )
                ->groupBy('ingredient_id')
                ->get()
                ->keyBy('ingredient_id');

            $ingredients = Ingredient::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            $priorityCounts = [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ];

            $manualReviewCount = 0;
            $purchaseRequiredCount = 0;
            $noPurchaseRequiredCount = 0;
            $totalRecommendedQuantity = 0.0;
            $totalEstimatedCost = 0.0;

            foreach ($ingredients as $ingredient) {
                $history = $consumption->get($ingredient->id);

                $totalConsumed = (float) ($history->total_consumed ?? 0);
                $consumptionDays = (int) ($history->consumption_days ?? 0);

                $hasRecipeUsage = DB::table('recipe_ingredients')
                    ->join('recipes', 'recipes.id', '=', 'recipe_ingredients.recipe_id')
                    ->where('recipe_ingredients.ingredient_id', $ingredient->id)
                    ->where('recipes.is_active', true)
                    ->exists();

                $hasAddOnUsage = DB::table('add_on_ingredients')
                    ->join('add_ons', 'add_ons.id', '=', 'add_on_ingredients.add_on_id')
                    ->where('add_on_ingredients.ingredient_id', $ingredient->id)
                    ->where('add_ons.is_available', true)
                    ->exists();

                $hasMenuUsage = $hasRecipeUsage || $hasAddOnUsage;

                $hasSufficientHistory = $consumptionDays
                    >= self::MINIMUM_CONSUMPTION_DAYS;

                $manualReview = ! $hasMenuUsage || ! $hasSufficientHistory;

                $currentStock = round((float) $ingredient->current_stock, 3);
                $minimumStock = round((float) $ingredient->minimum_stock, 3);
                $unitCost = round((float) $ingredient->cost_per_base_unit, 4);

                $dailyConsumption = null;
                $projectedDemand = null;
                $expectedStockoutDate = null;

                if (! $manualReview) {
                    $dailyConsumption = $totalConsumed / $lookbackDays;
                    $projectedDemand = $dailyConsumption * $forecastDays;

                    if ($dailyConsumption > 0) {
                        $daysUntilStockout = (int) floor(
                            $currentStock / $dailyConsumption
                        );

                        $expectedStockoutDate = $start
                            ->addDays($daysUntilStockout)
                            ->toDateString();
                    }

                    $targetStock = $projectedDemand + $minimumStock;
                } else {
                    // Without sufficient evidence, only flag a shortfall
                    // against the configured minimum stock level.
                    $targetStock = $minimumStock;
                    $manualReviewCount++;
                }

                $needed = max(0, $targetStock - $currentStock);

                $recommendedQuantity = $needed > 0
                    ? ceil(($needed * 1000) - 0.0000001) / 1000
                    : 0.0;

                $recommendedQuantity = round($recommendedQuantity, 3);
                $estimatedCost = round(
                    $recommendedQuantity * $unitCost,
                    2
                );

                if ($recommendedQuantity <= 0) {
                    $priority = 'low';
                    $recommendedOrderDate = null;
                    $noPurchaseRequiredCount++;

                    if ($manualReview) {
                        $reason = sprintf(
                            'Manual review required. %s. Current stock is %.3f %s and minimum stock is %.3f %s. No demand-based purchase quantity was calculated.',
                            $hasRecipeUsage
                                ? 'Historical order consumption is insufficient'
                                : 'No active recipe or available add-on ingredient usage is linked to this ingredient',
                            $currentStock,
                            $ingredient->base_unit,
                            $minimumStock,
                            $ingredient->base_unit
                        );
                    } else {
                        $reason = sprintf(
                            'Current stock of %.3f %s covers projected demand of %.3f %s plus minimum-stock buffer of %.3f %s. Historical order consumption: %.3f %s across %d consumption days.',
                            $currentStock,
                            $ingredient->base_unit,
                            $projectedDemand,
                            $ingredient->base_unit,
                            $minimumStock,
                            $ingredient->base_unit,
                            $totalConsumed,
                            $ingredient->base_unit,
                            $consumptionDays
                        );
                    }
                } else {
                    $purchaseRequiredCount++;
                    $recommendedOrderDate = $start->toDateString();

                    if ($manualReview) {
                        $priority = 'medium';

                        $reason = sprintf(
                            'Manual review required before purchasing. %s. Current stock %.3f %s is below minimum stock %.3f %s. The suggested quantity of %.3f %s only restores the minimum-stock level; it is not a demand forecast.',
                            $hasRecipeUsage
                                ? 'Historical order consumption is insufficient'
                                : 'No active recipe or available add-on ingredient usage is linked to this ingredient',
                            $currentStock,
                            $ingredient->base_unit,
                            $minimumStock,
                            $ingredient->base_unit,
                            $recommendedQuantity,
                            $ingredient->base_unit
                        );
                    } else {
                        $daysUntilStockout = $dailyConsumption > 0
                            ? (int) floor($currentStock / $dailyConsumption)
                            : null;

                        if ($daysUntilStockout !== null && $daysUntilStockout <= 7) {
                            $priority = 'critical';
                        } elseif ($daysUntilStockout !== null && $daysUntilStockout <= 14) {
                            $priority = 'high';
                        } elseif ($daysUntilStockout !== null && $daysUntilStockout <= 30) {
                            $priority = 'medium';
                        } else {
                            $priority = 'low';
                        }

                        $reason = sprintf(
                            'Projected demand of %.3f %s plus minimum-stock buffer of %.3f %s exceeds current stock of %.3f %s. Recommended replenishment: %.3f %s. Historical consumption: %.3f %s across %d consumption days.',
                            $projectedDemand,
                            $ingredient->base_unit,
                            $minimumStock,
                            $ingredient->base_unit,
                            $currentStock,
                            $ingredient->base_unit,
                            $recommendedQuantity,
                            $ingredient->base_unit,
                            $totalConsumed,
                            $ingredient->base_unit,
                            $consumptionDays
                        );
                    }
                }

                $priorityCounts[$priority]++;

                $totalRecommendedQuantity += $recommendedQuantity;
                $totalEstimatedCost += $estimatedCost;

                InventoryRecommendation::create([
                    'ingredient_id' => $ingredient->id,
                    'forecast_run_id' => $run->id,
                    'current_stock' => $currentStock,
                    'recommended_quantity' => $recommendedQuantity,
                    'estimated_cost' => $estimatedCost,
                    'recommended_order_date' => $recommendedOrderDate,
                    'expected_stockout_date' => $expectedStockoutDate,
                    'priority' => $priority,
                    'status' => 'pending',
                    'reason' => $reason,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            }

            $recommendationCount = InventoryRecommendation::query()
                ->where('forecast_run_id', $run->id)
                ->count();

            $summary = [
                'method' => 'historical_average_consumption',
                'historical_period' => [
                    'start' => $trainingStart->toDateString(),
                    'end' => $trainingEnd->toDateString(),
                ],
                'forecast_period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'historical_calendar_days' => $lookbackDays,
                'forecast_horizon_days' => $forecastDays,
                'demand_basis' => 'completed_order_stock_out_movements',
                'minimum_consumption_days' => self::MINIMUM_CONSUMPTION_DAYS,
                'data_label' => 'synthetic_simulated_historical_data',
                'recommendations_generated' => $recommendationCount,
                'manual_review_required' => $manualReviewCount,
                'purchase_required' => $purchaseRequiredCount,
                'no_purchase_required' => $noPurchaseRequiredCount,
                'priority_counts' => $priorityCounts,
                'total_recommended_quantity' => round($totalRecommendedQuantity, 3),
                'total_estimated_purchase_cost' => round($totalEstimatedCost, 2),
                'actual_purchase_created' => false,
                'accuracy_evaluated' => false,
            ];

            $run->update([
                'status' => 'completed',
                'results_summary' => $summary,
                'completed_at' => now(),
            ]);

            return $run->fresh();
        }, 3);
    }
}