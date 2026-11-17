<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryConsumptionService
{
    /**
     * Deduct all recipe and add-on ingredients for one fulfilled order.
     *
     * Quantities are aggregated in thousandths to preserve the database's
     * DECIMAL(12,3) precision.
     */
    public function consumeForOrder(int $orderId, ?int $userId = null): void
    {
        DB::transaction(function () use ($orderId, $userId) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === 'cancelled') {
                $this->reject('Cancelled orders cannot consume inventory.');
            }

            $alreadyConsumed = InventoryMovement::query()
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->where('movement_type', 'stock_out')
                ->exists();

            if ($alreadyConsumed) {
                $this->reject(
                    'Inventory has already been deducted for this order.'
                );
            }

            $order->load([
                'orderItems.addOns.addOn.addOnIngredients.ingredient',
            ]);

            if ($order->orderItems->isEmpty()) {
                $this->reject('Cannot complete an order without order items.');
            }

            $requirements = [];

            foreach ($order->orderItems as $item) {
                if ($item->quantity < 1) {
                    $this->reject(
                        "Invalid quantity for order item {$item->id}."
                    );
                }

                $recipeQuery = Recipe::query()
                    ->where('product_id', $item->product_id)
                    ->where('is_active', true);

                if ($item->product_variant_id === null) {
                    $recipeQuery->whereNull('product_variant_id');
                } else {
                    $recipeQuery->where(
                        'product_variant_id',
                        $item->product_variant_id
                    );
                }

                $recipe = $recipeQuery
                    ->with('recipeIngredients.ingredient')
                    ->first();

                if (!$recipe || $recipe->recipeIngredients->isEmpty()) {
                    $this->reject(
                        "Missing active recipe for {$item->product_name}"
                        . ($item->variant_name
                            ? " ({$item->variant_name})"
                            : '')
                        . '.'
                    );
                }

                foreach ($recipe->recipeIngredients as $recipeIngredient) {
                    $ingredient = $recipeIngredient->ingredient;

                    if (!$ingredient) {
                        $this->reject(
                            "Missing ingredient in recipe for {$item->product_name}."
                        );
                    }

                    $quantityMilli = $this->toMilli(
                        $recipeIngredient->quantity_required
                    ) * $item->quantity;

                    $this->addRequirement(
                        $requirements,
                        $ingredient,
                        $quantityMilli,
                        $recipeIngredient->unit
                    );
                }

                foreach ($item->addOns as $orderedAddOn) {
                    if ($orderedAddOn->quantity < 1) {
                        $this->reject(
                            "Invalid add-on quantity for {$orderedAddOn->add_on_name}."
                        );
                    }

                    $addOn = $orderedAddOn->addOn;

                    if (!$addOn) {
                        $this->reject(
                            "Add-on record is missing for {$orderedAddOn->add_on_name}."
                        );
                    }

                    $mappings = $addOn->addOnIngredients;

                    if ($mappings->isEmpty()) {
                        $this->reject(
                            "No ingredient mapping exists for add-on {$orderedAddOn->add_on_name}."
                        );
                    }

                    foreach ($mappings as $mapping) {
                        $ingredient = $mapping->ingredient;

                        if (!$ingredient) {
                            $this->reject(
                                "Missing ingredient mapping for {$orderedAddOn->add_on_name}."
                            );
                        }

                        $quantityMilli =
                            $this->toMilli($mapping->quantity_required)
                            * $item->quantity
                            * $orderedAddOn->quantity;

                        $this->addRequirement(
                            $requirements,
                            $ingredient,
                            $quantityMilli,
                            $mapping->unit
                        );
                    }
                }
            }

            if (empty($requirements)) {
                $this->reject(
                    'No ingredient requirements were found for this order.'
                );
            }

            $ingredientIds = array_keys($requirements);
            sort($ingredientIds);

            $ingredients = \App\Models\Ingredient::query()
                ->whereIn('id', $ingredientIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $ingredientId => $requirement) {
                $ingredient = $ingredients->get($ingredientId);

                if (!$ingredient) {
                    $this->reject(
                        "Ingredient {$requirement['name']} no longer exists."
                    );
                }

                if (!$ingredient->is_active) {
                    $this->reject(
                        "Ingredient {$ingredient->name} is inactive."
                    );
                }

                $stockMilli = $this->toMilli($ingredient->current_stock);

                if ($stockMilli < $requirement['quantity_milli']) {
                    $required = $this->formatMilli(
                        $requirement['quantity_milli']
                    );

                    $available = $this->formatMilli($stockMilli);

                    $this->reject(
                        "Insufficient stock for {$ingredient->name}. "
                        . "Required: {$required} {$ingredient->base_unit}; "
                        . "Available: {$available} {$ingredient->base_unit}."
                    );
                }
            }

            // All ingredients have passed validation.
            // Only now do we update stock and create movements.
            foreach ($requirements as $ingredientId => $requirement) {
                $ingredient = $ingredients->get($ingredientId);

                $stockBeforeMilli = $this->toMilli(
                    $ingredient->current_stock
                );

                $stockAfterMilli =
                    $stockBeforeMilli - $requirement['quantity_milli'];

                $unitCost = (float) $ingredient->cost_per_base_unit;

                $totalCost = round(
                    ($requirement['quantity_milli'] / 1000) * $unitCost,
                    2
                );

                $ingredient->update([
                    'current_stock' => $this->formatMilli($stockAfterMilli),
                ]);

                InventoryMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'user_id' => $userId,
                    'movement_type' => 'stock_out',
                    'quantity' => $this->formatMilli(
                        $requirement['quantity_milli']
                    ),
                    'stock_before' => $this->formatMilli($stockBeforeMilli),
                    'stock_after' => $this->formatMilli($stockAfterMilli),
                    'unit_cost' => number_format($unitCost, 4, '.', ''),
                    'total_cost' => number_format($totalCost, 2, '.', ''),
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reason' => "Ingredient consumption for order {$order->order_number}",
                    'movement_at' => now(),
                ]);

                app(LowStockRecommendationService::class)
                    ->syncForIngredient($ingredient);
            }
        }, 3);
    }

    /**
     * Add or combine one ingredient requirement.
     */
    private function addRequirement(
        array &$requirements,
        $ingredient,
        int $quantityMilli,
        string $unit
    ): void {
        if ($quantityMilli <= 0) {
            $this->reject(
                "Invalid ingredient quantity for {$ingredient->name}."
            );
        }

        if (
            strtolower(trim($unit))
            !== strtolower(trim($ingredient->base_unit))
        ) {
            $this->reject(
                "Unit mismatch for {$ingredient->name}. "
                . "Recipe/mapping unit: {$unit}; "
                . "ingredient base unit: {$ingredient->base_unit}."
            );
        }

        $id = $ingredient->id;

        if (!isset($requirements[$id])) {
            $requirements[$id] = [
                'name' => $ingredient->name,
                'quantity_milli' => 0,
            ];
        }

        $requirements[$id]['quantity_milli'] += $quantityMilli;
    }

    /**
     * Convert a DECIMAL(12,3) quantity to integer thousandths.
     */
    private function toMilli($quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    /**
     * Format integer thousandths as a three-decimal database value.
     */
    private function formatMilli(int $quantityMilli): string
    {
        return number_format($quantityMilli / 1000, 3, '.', '');
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([
            'inventory' => $message,
        ]);
    }
}