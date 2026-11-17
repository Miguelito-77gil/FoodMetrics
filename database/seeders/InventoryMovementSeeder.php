<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryMovementSeeder extends Seeder
{
    private function allocateChunks(array $events, int $chunks): array
    {
        $count = count($events);

        if ($chunks < 1 || $chunks > $count) {
            throw new RuntimeException('Invalid inventory movement chunk allocation.');
        }

        $groups = [];
        $baseSize = intdiv($count, $chunks);
        $remainder = $count % $chunks;
        $offset = 0;

        for ($i = 0; $i < $chunks; $i++) {
            $size = $baseSize + ($i < $remainder ? 1 : 0);
            $groups[] = array_slice($events, $offset, $size);
            $offset += $size;
        }

        return $groups;
    }

    private function allocateMovementCounts(array $eventsByIngredient, int $target): array
    {
        $counts = [];
        $totalEvents = 0;

        foreach ($eventsByIngredient as $ingredientId => $events) {
            $counts[$ingredientId] = 1;
            $totalEvents += count($events);
        }

        $remaining = $target - count($counts);
        $remainders = [];
        $allocated = 0;

        foreach ($eventsByIngredient as $ingredientId => $events) {
            $exact = $remaining * count($events) / $totalEvents;
            $whole = (int) floor($exact);

            $counts[$ingredientId] += $whole;
            $allocated += $whole;

            $remainders[] = [
                'id' => $ingredientId,
                'fraction' => $exact - $whole,
            ];
        }

        usort($remainders, function ($a, $b) {
            return $b['fraction'] <=> $a['fraction'];
        });

        $left = $remaining - $allocated;

        for ($i = 0; $i < $left; $i++) {
            $counts[$remainders[$i]['id']]++;
        }

        foreach ($counts as $ingredientId => $count) {
            if ($count > count($eventsByIngredient[$ingredientId])) {
                throw new RuntimeException(
                    "Movement allocation exceeds available events for ingredient {$ingredientId}."
                );
            }
        }

        return $counts;
    }

    public function run(): void
    {
        DB::transaction(function () {
            $ingredients = DB::table('ingredients')
                ->orderBy('id')
                ->get();

            if ($ingredients->count() !== 50) {
                throw new RuntimeException('Expected exactly 50 ingredients.');
            }

            $existing = DB::table('inventory_movements')->get();

            if ($existing->isNotEmpty()) {
                $owned = $existing->every(fn ($row) =>
                    in_array($row->reference_type, [
                        'opening_balance',
                        'completed_order_consumption',
                    ], true)
                );

                if (!$owned) {
                    throw new RuntimeException(
                        'Existing inventory movements include records not owned by this seeder.'
                    );
                }

                DB::table('inventory_movements')->delete();
            }

            $inventoryStaff = DB::table('users')
                ->where('role', 'inventory_staff')
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (!$inventoryStaff) {
                throw new RuntimeException('No active inventory staff account found.');
            }

            $completedOrders = DB::table('orders')
                ->where('status', 'completed')
                ->orderBy('completed_at')
                ->orderBy('id')
                ->get();

            if ($completedOrders->count() !== 135) {
                throw new RuntimeException('Expected exactly 135 completed orders.');
            }

            $eventsByIngredient = [];

            foreach ($completedOrders as $order) {
                $items = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->get();

                foreach ($items as $item) {
                    $recipeQuery = DB::table('recipes')
                        ->where('product_id', $item->product_id)
                        ->where('is_active', true);

                    if ($item->product_variant_id !== null) {
                        $recipeQuery->where(
                            'product_variant_id',
                            $item->product_variant_id
                        );
                    } else {
                        $recipeQuery->whereNull('product_variant_id');
                    }

                    $recipe = $recipeQuery->first();

                    if (!$recipe) {
                        throw new RuntimeException(
                            "Missing recipe for order item {$item->id}."
                        );
                    }

                    $recipeIngredients = DB::table('recipe_ingredients')
                        ->where('recipe_id', $recipe->id)
                        ->get();

                    if ($recipeIngredients->isEmpty()) {
                        throw new RuntimeException(
                            "Recipe {$recipe->id} has no ingredients."
                        );
                    }

                    foreach ($recipeIngredients as $recipeIngredient) {
                        $quantity = round(
                            (float) $recipeIngredient->quantity_required
                            * (int) $item->quantity,
                            3
                        );

                        if ($quantity <= 0) {
                            throw new RuntimeException('Invalid consumption quantity.');
                        }

                        $eventsByIngredient[$recipeIngredient->ingredient_id][] = [
                            'quantity' => $quantity,
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'movement_at' => $order->completed_at ?? $order->ordered_at,
                        ];
                    }
                }
            }

            if (count($eventsByIngredient) !== 49) {
                throw new RuntimeException(
                    'Expected 49 ingredients to be used by completed orders.'
                );
            }

            foreach ($eventsByIngredient as &$events) {
                usort($events, function ($a, $b) {
                    $dateCompare = strcmp($a['movement_at'], $b['movement_at']);

                    if ($dateCompare !== 0) {
                        return $dateCompare;
                    }

                    return $a['order_id'] <=> $b['order_id'];
                });
            }
            unset($events);

            $movementCounts = $this->allocateMovementCounts(
                $eventsByIngredient,
                150
            );

            $ingredientMap = $ingredients->keyBy('id');
            $openingRows = [];
            $stockOutRows = [];
            $totalConsumption = 0;
            $totalOpeningStock = 0;

            foreach ($ingredients as $ingredient) {
                $events = $eventsByIngredient[$ingredient->id] ?? [];
                $consumption = round(
                    array_sum(array_column($events, 'quantity')),
                    3
                );

                $currentStock = round((float) $ingredient->current_stock, 3);
                $openingStock = round($currentStock + $consumption, 3);

                if ($openingStock <= 0 || $currentStock < 0) {
                    throw new RuntimeException(
                        "Invalid stock calculation for {$ingredient->name}."
                    );
                }

                $unitCost = (float) $ingredient->cost_per_base_unit;

                $openingRows[] = [
                    'ingredient_id' => $ingredient->id,
                    'user_id' => $inventoryStaff->id,
                    'movement_type' => 'stock_in',
                    'quantity' => number_format($openingStock, 3, '.', ''),
                    'stock_before' => '0.000',
                    'stock_after' => number_format($openingStock, 3, '.', ''),
                    'unit_cost' => number_format($unitCost, 4, '.', ''),
                    'total_cost' => number_format($openingStock * $unitCost, 2, '.', ''),
                    'reference_type' => 'opening_balance',
                    'reference_id' => null,
                    'reason' => 'Synthetic opening inventory balance before the historical reporting period.',
                    'movement_at' => '2025-10-31 23:59:59',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $totalOpeningStock += $openingStock;
                $totalConsumption += $consumption;

                if (!$events) {
                    continue;
                }

                $groups = $this->allocateChunks(
                    $events,
                    $movementCounts[$ingredient->id]
                );

                $runningStock = $openingStock;

                foreach ($groups as $group) {
                    $quantity = round(
                        array_sum(array_column($group, 'quantity')),
                        3
                    );

                    $lastEvent = end($group);
                    $stockAfter = round($runningStock - $quantity, 3);

                    if ($stockAfter < -0.001) {
                        throw new RuntimeException(
                            "Insufficient stock for {$ingredient->name}."
                        );
                    }

                    $stockOutRows[] = [
                        'ingredient_id' => $ingredient->id,
                        'user_id' => $inventoryStaff->id,
                        'movement_type' => 'stock_out',
                        'quantity' => number_format($quantity, 3, '.', ''),
                        'stock_before' => number_format($runningStock, 3, '.', ''),
                        'stock_after' => number_format(max(0, $stockAfter), 3, '.', ''),
                        'unit_cost' => number_format($unitCost, 4, '.', ''),
                        'total_cost' => number_format($quantity * $unitCost, 2, '.', ''),
                        'reference_type' => 'completed_order_consumption',
                        'reference_id' => $lastEvent['order_id'],
                        'reason' => 'Aggregated recipe ingredient consumption from fulfilled historical orders.',
                        'movement_at' => $lastEvent['movement_at'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $runningStock = max(0, $stockAfter);
                }

                if (abs($runningStock - $currentStock) >= 0.001) {
                    throw new RuntimeException(
                        "Ending stock does not reconcile for {$ingredient->name}."
                    );
                }
            }

            if (count($openingRows) !== 50 || count($stockOutRows) !== 150) {
                throw new RuntimeException(
                    'Movement record target mismatch. Expected 50 opening and 150 stock-out records.'
                );
            }

            DB::table('inventory_movements')->insert($openingRows);

            foreach (array_chunk($stockOutRows, 100) as $chunk) {
                DB::table('inventory_movements')->insert($chunk);
            }

            $this->command->info('Completed orders processed: ' . $completedOrders->count());
            $this->command->info('Ingredients with consumption: ' . count($eventsByIngredient));
            $this->command->info('Opening stock-in records: ' . count($openingRows));
            $this->command->info('Aggregated stock-out records: ' . count($stockOutRows));
            $this->command->info('Total inventory movements: ' . (count($openingRows) + count($stockOutRows)));
            $this->command->info('Total opening stock quantity: ' . number_format($totalOpeningStock, 3));
            $this->command->info('Total completed-order consumption: ' . number_format($totalConsumption, 3));
        });
    }
}