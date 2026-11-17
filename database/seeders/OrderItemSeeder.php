<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderItemSeeder extends Seeder
{
    public function run(): void
    {
        $orders = DB::table('orders')
            ->orderBy('order_number')
            ->get();

        $products = DB::table('products')
            ->where('is_available', true)
            ->orderBy('id')
            ->get()
            ->values();

        $variantsByProduct = DB::table('product_variants')
            ->where('is_available', true)
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        if ($orders->count() !== 150) {
            throw new RuntimeException('Expected exactly 150 orders before generating order items.');
        }

        if ($products->count() < 3) {
            throw new RuntimeException('Insufficient available products.');
        }

        $itemRows = [];
        $orderUpdates = [];
        $itemCount = 0;

        DB::transaction(function () use (
            $orders,
            $products,
            $variantsByProduct,
            &$itemRows,
            &$orderUpdates,
            &$itemCount
        ) {
            $orderIds = $orders->pluck('id')->all();

            // Safe to rerun: replace only items belonging to these orders.
            DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->delete();

            foreach ($orders as $orderIndex => $order) {
                $sequence = $orderIndex + 1;

                // Every order receives two items; the first 50 receive a third.
                $positions = [0, 13];

                if ($sequence <= 50) {
                    $positions[] = 27;
                }

                $orderSubtotal = 0.00;

                foreach ($positions as $position => $offset) {
                    $productIndex = (($sequence * 7) + $offset) % $products->count();
                    $product = $products[$productIndex];

                    $variant = null;
                    $availableVariants = $variantsByProduct->get($product->id, collect());

                    // Apply a variant to selected products when one is available.
                    if (
                        $availableVariants->isNotEmpty()
                        && (($sequence + $position) % 3 === 0)
                    ) {
                        $variantIndex = ($sequence + $position) % $availableVariants->count();
                        $variant = $availableVariants->values()[$variantIndex];
                    }

                    $unitPrice = round(
                        (float) $product->base_price
                        + ($variant ? (float) $variant->price_adjustment : 0),
                        2
                    );

                    if ($unitPrice <= 0) {
                        throw new RuntimeException(
                            "Invalid calculated price for product {$product->product_code}."
                        );
                    }

                    $quantity = 1 + (($sequence + $position) % 2);
                    $lineSubtotal = round($unitPrice * $quantity, 2);

                    $orderSubtotal = round($orderSubtotal + $lineSubtotal, 2);

                    $itemRows[] = [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant?->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                        'notes' => 'Simulated historical order item.',
                        'created_at' => $order->ordered_at,
                        'updated_at' => $order->ordered_at,
                    ];

                    $itemCount++;
                }

                $taxAmount = round($orderSubtotal * 0.12, 2);

                $totalAmount = round(
                    $orderSubtotal
                    - (float) $order->discount_amount
                    + $taxAmount
                    + (float) $order->delivery_fee,
                    2
                );

                $orderUpdates[] = [
                    'id' => $order->id,
                    'subtotal' => $orderSubtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'updated_at' => $order->ordered_at,
                ];
            }

            foreach (array_chunk($itemRows, 100) as $chunk) {
                DB::table('order_items')->insert($chunk);
            }

            foreach ($orderUpdates as $update) {
                DB::table('orders')
                    ->where('id', $update['id'])
                    ->update([
                        'subtotal' => $update['subtotal'],
                        'tax_amount' => $update['tax_amount'],
                        'total_amount' => $update['total_amount'],
                        'updated_at' => $update['updated_at'],
                    ]);
            }
        });

        $this->command->info("{$itemCount} simulated historical order items created successfully.");
        $this->command->info('Order subtotals, modeled tax, and total amounts recalculated.');
    }
}