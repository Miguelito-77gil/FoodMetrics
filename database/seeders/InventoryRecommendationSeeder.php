<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryRecommendationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            if (DB::table('inventory_recommendations')->exists()) {
                throw new RuntimeException(
                    'Inventory recommendations already exist. Seeder stopped.'
                );
            }

            $run = DB::table('forecast_runs')
                ->where('forecast_type', 'inventory')
                ->first();

            if (
                !$run ||
                $run->status !== 'pending' ||
                $run->forecast_start_date !== '2026-11-01' ||
                $run->forecast_end_date !== '2026-11-30'
            ) {
                throw new RuntimeException(
                    'Inventory forecast run is missing or has an unexpected configuration.'
                );
            }

            $ingredients = DB::table('ingredients as i')
                ->join('recipe_ingredients as ri', 'i.id', '=', 'ri.ingredient_id')
                ->select('i.*')
                ->distinct()
                ->orderBy('i.id')
                ->get();

            if ($ingredients->count() !== 49) {
                throw new RuntimeException(
                    "Expected 49 recipe-linked ingredients, found {$ingredients->count()}."
                );
            }

            $unused = DB::table('ingredients as i')
                ->leftJoin('recipe_ingredients as ri', 'i.id', '=', 'ri.ingredient_id')
                ->whereNull('ri.id')
                ->select('i.*')
                ->get();

            if (
                $unused->count() !== 1 ||
                $unused->first()->name !== 'Banana'
            ) {
                throw new RuntimeException(
                    'Expected Banana to be the only unused ingredient.'
                );
            }

            $banana = $unused->first();

            if (
                (float) $banana->current_stock !== 6.0 ||
                (float) $banana->minimum_stock !== 2.0
            ) {
                throw new RuntimeException(
                    'Banana stock changed unexpectedly. Seeder stopped.'
                );
            }

            $historicalStart = '2025-11-01 00:00:00';
            $historicalEnd = '2026-10-31 23:59:59';

            $consumption = DB::table('inventory_movements')
                ->where('movement_type', 'stock_out')
                ->whereBetween('movement_at', [
                    $historicalStart,
                    $historicalEnd,
                ])
                ->selectRaw('ingredient_id, SUM(quantity) AS total_consumed')
                ->groupBy('ingredient_id')
                ->get()
                ->keyBy('ingredient_id');

            $historicalDays = 365;
            $forecastDays = 30;

            $forecastStart = Carbon::parse('2026-11-01');
            $forecastEnd = Carbon::parse('2026-11-30');

            $now = now();
            $records = [];

            $priorityCounts = [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ];

            $purchaseRequired = 0;
            $noPurchaseRequired = 0;
            $totalRecommendedCost = 0;
            $totalRecommendedQuantity = 0;

            foreach ($ingredients as $ingredient) {
                $totalConsumed = (float) (
                    $consumption->get($ingredient->id)->total_consumed ?? 0
                );

                $dailyConsumption = $totalConsumed / $historicalDays;
                $projectedDemand = $dailyConsumption * $forecastDays;

                $currentStock = (float) $ingredient->current_stock;
                $minimumStock = (float) $ingredient->minimum_stock;
                $unitCost = (float) $ingredient->cost_per_base_unit;

                // Target stock = 30-day projected demand + minimum stock buffer.
                $targetStock = $projectedDemand + $minimumStock;

                $needed = max(0, $targetStock - $currentStock);

                // Round up to the nearest 0.001 base unit to avoid under-ordering.
                $recommendedQuantity = $needed > 0
                    ? ceil(($needed * 1000) - 0.0000001) / 1000
                    : 0;

                $estimatedCost = round(
                    $recommendedQuantity * $unitCost,
                    2
                );

                $daysUntilStockout = null;
                $expectedStockoutDate = null;

                if ($dailyConsumption > 0) {
                    $daysUntilStockout = (int) floor(
                        $currentStock / $dailyConsumption
                    );

                    $expectedStockoutDate = $forecastStart
                        ->copy()
                        ->addDays($daysUntilStockout)
                        ->toDateString();
                }

                if ($recommendedQuantity <= 0) {
                    $priority = 'low';
                    $noPurchaseRequired++;
                    $recommendedOrderDate = null;

                    $reason = sprintf(
                        'Current stock of %.3f %s covers projected 30-day demand of %.3f %s and the minimum-stock buffer of %.3f %s. No additional purchase is currently required. Historical annual consumption: %.3f %s.',
                        $currentStock,
                        $ingredient->base_unit,
                        $projectedDemand,
                        $ingredient->base_unit,
                        $minimumStock,
                        $ingredient->base_unit,
                        $totalConsumed,
                        $ingredient->base_unit
                    );
                } else {
                    $purchaseRequired++;
                    $recommendedOrderDate = $forecastStart->toDateString();

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
                        'Projected 30-day demand of %.3f %s plus minimum-stock buffer of %.3f %s exceeds current stock of %.3f %s. Recommended replenishment: %.3f %s. Historical annual consumption: %.3f %s.',
                        $projectedDemand,
                        $ingredient->base_unit,
                        $minimumStock,
                        $ingredient->base_unit,
                        $currentStock,
                        $ingredient->base_unit,
                        $recommendedQuantity,
                        $ingredient->base_unit,
                        $totalConsumed,
                        $ingredient->base_unit
                    );
                }

                $priorityCounts[$priority]++;

                $totalRecommendedQuantity += $recommendedQuantity;
                $totalRecommendedCost += $estimatedCost;

                $records[] = [
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($records) !== 49) {
                throw new RuntimeException(
                    'Expected exactly 49 inventory recommendations.'
                );
            }

            DB::table('inventory_recommendations')->insert($records);

            $createdCount = DB::table('inventory_recommendations')
                ->where('forecast_run_id', $run->id)
                ->count();

            if ($createdCount !== 49) {
                throw new RuntimeException(
                    "Expected 49 saved recommendations, found {$createdCount}."
                );
            }

            $summary = [
                'method' => 'reorder_point',
                'historical_period' => '2025-11-01 to 2026-10-31',
                'forecast_period' => '2026-11-01 to 2026-11-30',
                'historical_calendar_days' => 365,
                'forecast_horizon_days' => 30,
                'demand_basis' => 'historical_stock_out_consumption',
                'daily_average_method' => 'total_historical_consumption_divided_by_365',
                'target_stock_formula' => 'projected_30_day_demand_plus_minimum_stock',
                'recommended_quantity_formula' => 'max(0,target_stock_minus_current_stock)',
                'rounding' => 'rounded_up_to_0.001_base_unit',
                'data_label' => 'synthetic_simulated_historical_data',
                'recommendations_generated' => $createdCount,
                'purchase_required' => $purchaseRequired,
                'no_purchase_required' => $noPurchaseRequired,
                'manual_review_ingredients' => [
                    [
                        'ingredient' => 'Banana',
                        'ingredient_id' => $banana->id,
                        'classification' => 'no_recipe_usage',
                        'reason' => 'No recipe links and no historical stock-out consumption.',
                        'current_stock_preserved' => (float) $banana->current_stock,
                        'base_unit' => $banana->base_unit,
                    ],
                ],
                'priority_counts' => $priorityCounts,
                'total_recommended_quantity' => round($totalRecommendedQuantity, 3),
                'total_estimated_purchase_cost' => round($totalRecommendedCost, 2),
                'actual_purchase_created' => false,
            ];

            DB::table('forecast_runs')
                ->where('id', $run->id)
                ->update([
                    'status' => 'completed',
                    'results_summary' => json_encode($summary),
                    'error_message' => null,
                    'started_at' => $now,
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);

            echo "Recipe-linked ingredients: 49" . PHP_EOL;
            echo "Manual review ingredients: 1 (Banana)" . PHP_EOL;
            echo "Inventory recommendations created: {$createdCount}" . PHP_EOL;
            echo "Purchase required: {$purchaseRequired}" . PHP_EOL;
            echo "No purchase required: {$noPurchaseRequired}" . PHP_EOL;

            foreach ($priorityCounts as $priority => $count) {
                echo ucfirst($priority) . " priority: {$count}" . PHP_EOL;
            }

            echo "Total recommended quantity: "
                . number_format($totalRecommendedQuantity, 3)
                . PHP_EOL;

            echo "Total estimated purchase cost: "
                . number_format($totalRecommendedCost, 2)
                . PHP_EOL;

            echo "Banana stock preserved: 6.000 kg" . PHP_EOL;
            echo "Inventory forecast status: completed" . PHP_EOL;
        });
    }
}