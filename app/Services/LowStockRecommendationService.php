<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryRecommendation;

class LowStockRecommendationService
{
    public function syncForIngredient(Ingredient $ingredient): ?InventoryRecommendation
    {
        $currentStock = (float) $ingredient->current_stock;
        $minimumStock = (float) $ingredient->minimum_stock;

        if (! $ingredient->is_active || $currentStock > $minimumStock) {
            return null;
        }

        $recommendation = InventoryRecommendation::query()
            ->where('ingredient_id', $ingredient->id)
            ->whereNull('forecast_run_id')
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        if (! $recommendation) {
            $hasPendingForecastRecommendation = InventoryRecommendation::query()
                ->where('ingredient_id', $ingredient->id)
                ->whereNotNull('forecast_run_id')
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingForecastRecommendation) {
                return null;
            }
        }

        $quantity = $minimumStock;
        $cost = $quantity * (float) $ingredient->cost_per_base_unit;

        $values = [
            'current_stock' => $currentStock,
            'recommended_quantity' => $quantity,
            'estimated_cost' => $cost,
            'recommended_order_date' => now()->toDateString(),
            'expected_stockout_date' => null,
            'priority' => 'medium',
            'status' => 'pending',
            'reason' => 'Automatically detected after order completion: ingredient stock is at or below its minimum level.',
        ];

        if ($recommendation) {
            $recommendation->update($values);

            return $recommendation->fresh();
        }

        return InventoryRecommendation::create([
            'ingredient_id' => $ingredient->id,
            ...$values,
        ]);
    }
}
