<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransactionService
{
    private const MAX_STOCK = 999999999.999;
    private const MAX_UNIT_COST = 99999999.9999;
    private const MAX_TOTAL_COST = 9999999999.99;

    public function stockIn(
        int $ingredientId,
        int $userId,
        float $quantity,
        float $unitCost,
        string $reason
    ): InventoryMovement {
        return DB::transaction(function () use (
            $ingredientId,
            $userId,
            $quantity,
            $unitCost,
            $reason
        ) {
            $ingredient = $this->lockIngredient($ingredientId);
            $this->ensureActive($ingredient);

            $quantity = round($quantity, 3);
            $unitCost = round($unitCost, 4);
            $before = round((float) $ingredient->current_stock, 3);
            $oldCost = round((float) $ingredient->cost_per_base_unit, 4);
            $after = round($before + $quantity, 3);

            $this->validateQuantity($quantity);
            $this->validateUnitCost($unitCost);
            $this->validateStock($after);

            $totalCost = round($quantity * $unitCost, 2);

            if ($totalCost > self::MAX_TOTAL_COST) {
                throw ValidationException::withMessages([
                    'unit_cost' => 'The transaction cost exceeds the supported limit.',
                ]);
            }

            $newAverageCost = $after > 0
                ? round((($before * $oldCost) + ($quantity * $unitCost)) / $after, 4)
                : $unitCost;

            $ingredient->update([
                'current_stock' => $after,
                'cost_per_base_unit' => $newAverageCost,
            ]);

            return $this->createMovement(
                $ingredient,
                $userId,
                'stock_in',
                $quantity,
                $before,
                $after,
                $unitCost,
                $totalCost,
                'manual_stock_in',
                $reason
            );
        }, 3);
    }

    public function adjust(
        int $ingredientId,
        int $userId,
        string $direction,
        float $quantity,
        string $reason
    ): InventoryMovement {
        return DB::transaction(function () use (
            $ingredientId,
            $userId,
            $direction,
            $quantity,
            $reason
        ) {
            $ingredient = $this->lockIngredient($ingredientId);
            $this->ensureActive($ingredient);

            if (! in_array($direction, ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages([
                    'direction' => 'Select a valid adjustment direction.',
                ]);
            }

            $quantity = round($quantity, 3);
            $before = round((float) $ingredient->current_stock, 3);
            $after = round(
                $direction === 'increase'
                    ? $before + $quantity
                    : $before - $quantity,
                3
            );

            $this->validateQuantity($quantity);
            $this->validateStock($after);

            $unitCost = round((float) $ingredient->cost_per_base_unit, 4);
            $totalCost = round($quantity * $unitCost, 2);

            if ($totalCost > self::MAX_TOTAL_COST) {
                throw ValidationException::withMessages([
                    'quantity' => 'The adjustment value exceeds the supported limit.',
                ]);
            }

            $ingredient->update([
                'current_stock' => $after,
            ]);

            return $this->createMovement(
                $ingredient,
                $userId,
                'adjustment',
                $quantity,
                $before,
                $after,
                $unitCost,
                $totalCost,
                'manual_adjustment',
                $reason
            );
        }, 3);
    }

    public function recordWaste(
        int $ingredientId,
        int $userId,
        float $quantity,
        string $reason
    ): InventoryMovement {
        return DB::transaction(function () use (
            $ingredientId,
            $userId,
            $quantity,
            $reason
        ) {
            $ingredient = $this->lockIngredient($ingredientId);
            $this->ensureActive($ingredient);

            $quantity = round($quantity, 3);
            $before = round((float) $ingredient->current_stock, 3);
            $after = round($before - $quantity, 3);
            $unitCost = round((float) $ingredient->cost_per_base_unit, 4);

            $this->validateQuantity($quantity);
            $this->validateStock($after);

            $totalCost = round($quantity * $unitCost, 2);

            if ($totalCost > self::MAX_TOTAL_COST) {
                throw ValidationException::withMessages([
                    'quantity' => 'The waste value exceeds the supported limit.',
                ]);
            }

            $ingredient->update([
                'current_stock' => $after,
            ]);

            return $this->createMovement(
                $ingredient,
                $userId,
                'waste',
                $quantity,
                $before,
                $after,
                $unitCost,
                $totalCost,
                'manual_waste',
                $reason
            );
        }, 3);
    }

    private function lockIngredient(int $ingredientId): Ingredient
    {
        return Ingredient::query()
            ->lockForUpdate()
            ->findOrFail($ingredientId);
    }

    private function ensureActive(Ingredient $ingredient): void
    {
        if (! $ingredient->is_active) {
            throw ValidationException::withMessages([
                'ingredient_id' => 'Inactive ingredients cannot receive manual stock transactions.',
            ]);
        }
    }

    private function validateQuantity(float $quantity): void
    {
        if (! is_finite($quantity) || $quantity < 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 0.001.',
            ]);
        }
    }

    private function validateUnitCost(float $unitCost): void
    {
        if (
            ! is_finite($unitCost)
            || $unitCost < 0
            || $unitCost > self::MAX_UNIT_COST
        ) {
            throw ValidationException::withMessages([
                'unit_cost' => 'Enter a valid unit cost.',
            ]);
        }
    }

    private function validateStock(float $stock): void
    {
        if (! is_finite($stock) || $stock < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'The transaction cannot reduce stock below zero.',
            ]);
        }

        if ($stock > self::MAX_STOCK) {
            throw ValidationException::withMessages([
                'quantity' => 'The resulting stock exceeds the supported limit.',
            ]);
        }
    }

    private function createMovement(
        Ingredient $ingredient,
        int $userId,
        string $type,
        float $quantity,
        float $before,
        float $after,
        float $unitCost,
        float $totalCost,
        string $reference,
        string $reason
    ): InventoryMovement {
        return InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'user_id' => $userId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reference_type' => $reference,
            'reference_id' => null,
            'reason' => trim($reason),
            'movement_at' => now(),
        ]);
    }
}