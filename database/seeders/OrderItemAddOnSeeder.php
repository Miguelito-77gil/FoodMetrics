<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderItemAddOnSeeder extends Seeder
{
    public function run(): void
    {
        $orderItems = DB::table('order_items')
            ->orderBy('id')
            ->get();

        if ($orderItems->count() !== 350) {
            throw new RuntimeException('Expected exactly 350 order items.');
        }

        $eligibleAddOns = DB::table('product_add_ons as pa')
            ->join('add_ons as a', 'pa.add_on_id', '=', 'a.id')
            ->where('a.is_available', true)
            ->select(
                'pa.product_id',
                'a.id as add_on_id',
                'a.name',
                'a.price'
            )
            ->orderBy('pa.product_id')
            ->orderBy('a.id')
            ->get()
            ->groupBy('product_id');

        $rows = [];
        $eligibleIndex = 0;

        foreach ($orderItems as $item) {
            $available = $eligibleAddOns->get($item->product_id, collect());

            if ($available->isEmpty()) {
                continue;
            }

            // Select approximately one-third of eligible order items.
            if ($eligibleIndex % 3 === 0) {
                $selected = [$available->first()];

                // Add a second distinct add-on occasionally.
                if ($eligibleIndex % 12 === 0 && $available->count() > 1) {
                    $selected[] = $available->get(1);
                }

                $quantity = ($eligibleIndex % 4 === 0) ? 2 : 1;

                foreach ($selected as $addOn) {
                    $unitPrice = (float) $addOn->price;

                    $rows[] = [
                        'order_item_id' => $item->id,
                        'add_on_id' => $addOn->add_on_id,
                        'add_on_name' => $addOn->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => round($unitPrice * $quantity, 2),
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                }
            }

            $eligibleIndex++;
        }

        if (count($rows) < 90 || count($rows) > 140) {
            throw new RuntimeException(
                'Generated add-on count is outside the approved 90–140 range: ' . count($rows)
            );
        }

        DB::transaction(function () use ($orderItems, $rows) {
            $itemIds = $orderItems->pluck('id')->all();

            // Remove previous records so rerunning this seeder is safe.
            DB::table('order_item_add_ons')
                ->whereIn('order_item_id', $itemIds)
                ->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('order_item_add_ons')->insert($chunk);
            }

            // Recalculate every order using product items + add-ons.
            $itemTotals = DB::table('order_items')
                ->selectRaw('order_id, SUM(subtotal) as amount')
                ->groupBy('order_id')
                ->pluck('amount', 'order_id');

            $addOnTotals = DB::table('order_item_add_ons as oa')
                ->join('order_items as oi', 'oa.order_item_id', '=', 'oi.id')
                ->selectRaw('oi.order_id, SUM(oa.subtotal) as amount')
                ->groupBy('oi.order_id')
                ->pluck('amount', 'order_id');

            $orders = DB::table('orders')->get([
                'id',
                'discount_amount',
                'delivery_fee',
            ]);

            foreach ($orders as $order) {
                $productSubtotal = (float) ($itemTotals[$order->id] ?? 0);
                $addOnSubtotal = (float) ($addOnTotals[$order->id] ?? 0);

                $subtotal = round($productSubtotal + $addOnSubtotal, 2);
                $tax = round($subtotal * 0.12, 2);

                $total = round(
                    $subtotal
                    - (float) $order->discount_amount
                    + $tax
                    + (float) $order->delivery_fee,
                    2
                );

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'subtotal' => $subtotal,
                        'tax_amount' => $tax,
                        'total_amount' => $total,
                    ]);
            }
        });

        $this->command->info(
            count($rows) . ' simulated order item add-ons created successfully.'
        );

        $this->command->info(
            'Order subtotals, modeled tax, and total amounts recalculated.'
        );
    }
}