<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

class PromotionRedemptionSeeder extends Seeder
{
    private function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function calculateDiscount(object $promotion, float $subtotal): float
    {
        if ($promotion->discount_type === 'percentage') {
            $discount = round(
                $subtotal * ((float) $promotion->discount_value / 100),
                2
            );

            if ($promotion->maximum_discount_amount !== null) {
                $discount = min(
                    $discount,
                    (float) $promotion->maximum_discount_amount
                );
            }
        } else {
            $discount = (float) $promotion->discount_value;
        }

        return round(min($discount, $subtotal), 2);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $promotions = DB::table('promotions')
                ->orderBy('id')
                ->get();

            $orders = DB::table('orders')
                ->where('status', 'completed')
                ->orderBy('ordered_at')
                ->orderBy('id')
                ->get();

            if ($promotions->count() !== 10 || $orders->count() !== 135) {
                throw new RuntimeException(
                    'Unexpected promotion or completed order count.'
                );
            }

            $usage = [];
            $planned = [];

            foreach ($promotions as $promotion) {
                $usage[$promotion->id] = 0;
            }

            foreach ($orders as $order) {
                $existingDiscount = (float) $order->discount_amount;

                $existingRedemptions = DB::table('promotion_redemptions')
                    ->where('order_id', $order->id)
                    ->sum('discount_amount');

                if (abs($existingDiscount - (float) $existingRedemptions) >= 0.01) {
                    throw new RuntimeException(
                        "Existing discount/redemption mismatch: {$order->order_number}"
                    );
                }

                $date = substr($order->ordered_at, 0, 10);
                $subtotal = (float) $order->subtotal;
                $candidates = [];

                foreach ($promotions as $promotion) {
                    if ($date < $promotion->start_date) {
                        continue;
                    }

                    if ($promotion->end_date !== null && $date > $promotion->end_date) {
                        continue;
                    }

                    if ($subtotal < (float) $promotion->minimum_order_amount) {
                        continue;
                    }

                    $discount = $this->calculateDiscount($promotion, $subtotal);

                    if ($discount <= 0) {
                        continue;
                    }

                    $limit = $promotion->usage_limit;

                    if ($limit !== null && $usage[$promotion->id] >= (int) $limit) {
                        continue;
                    }

                    $candidates[] = [
                        'promotion' => $promotion,
                        'discount' => $discount,
                    ];
                }

                usort($candidates, function ($a, $b) {
                    $difference = $b['discount'] <=> $a['discount'];

                    if ($difference !== 0) {
                        return $difference;
                    }

                    return $a['promotion']->id <=> $b['promotion']->id;
                });

                $selected = $candidates[0] ?? null;

                if ($selected) {
                    $promotion = $selected['promotion'];

                    $planned[$order->id] = [
                        'promotion_id' => $promotion->id,
                        'customer_id' => $order->customer_id,
                        'discount' => $selected['discount'],
                        'redeemed_at' => $order->ordered_at,
                        'notes' => 'Synthetic historical promotion redemption.',
                    ];

                    $usage[$promotion->id]++;
                }
            }

            $orderIds = $orders->pluck('id')->all();

            foreach ($orders as $order) {
                $payments = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->orderBy('id')
                    ->get();

                if ($payments->isEmpty()) {
                    throw new RuntimeException(
                        "No completed payments found for {$order->order_number}."
                    );
                }

                $oldTotalCents = $this->cents($order->total_amount);
                $paymentTotalCents = $payments->sum(
                    fn ($payment) => $this->cents($payment->amount)
                );

                if (abs($oldTotalCents - $paymentTotalCents) > 1) {
                    throw new RuntimeException(
                        "Payment mismatch before adjustment: {$order->order_number}"
                    );
                }

                $discount = $planned[$order->id]['discount'] ?? 0;

                $newTotal = round(
                    (float) $order->subtotal
                    - $discount
                    + (float) $order->tax_amount
                    + (float) $order->delivery_fee,
                    2
                );

                $newTotalCents = $this->cents($newTotal);

                if ($newTotalCents < 0) {
                    throw new RuntimeException(
                        "Invalid calculated total: {$order->order_number}"
                    );
                }

                $allocatedCents = 0;
                $lastIndex = $payments->count() - 1;

                foreach ($payments as $index => $payment) {
                    if ($index === $lastIndex) {
                        $newPaymentCents = $newTotalCents - $allocatedCents;
                    } else {
                        $oldPaymentCents = $this->cents($payment->amount);

                        $newPaymentCents = $oldTotalCents > 0
                            ? (int) round(
                                $newTotalCents * $oldPaymentCents / $oldTotalCents
                            )
                            : 0;

                        $allocatedCents += $newPaymentCents;
                    }

                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update([
                            'amount' => number_format($newPaymentCents / 100, 2, '.', ''),
                            'updated_at' => now(),
                        ]);
                }

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'discount_amount' => number_format($discount, 2, '.', ''),
                        'total_amount' => number_format($newTotal, 2, '.', ''),
                        'updated_at' => now(),
                    ]);
            }

            // Replace only completed-order redemptions owned by this seeder.
            DB::table('promotion_redemptions')
                ->whereIn('order_id', $orderIds)
                ->delete();

            $redemptionRows = [];

            foreach ($planned as $orderId => $redemption) {
                $redemptionRows[] = [
                    'promotion_id' => $redemption['promotion_id'],
                    'order_id' => $orderId,
                    'customer_id' => $redemption['customer_id'],
                    'discount_amount' => number_format(
                        $redemption['discount'],
                        2,
                        '.',
                        ''
                    ),
                    'redeemed_at' => $redemption['redeemed_at'],
                    'notes' => $redemption['notes'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($redemptionRows, 100) as $chunk) {
                DB::table('promotion_redemptions')->insert($chunk);
            }

            foreach ($promotions as $promotion) {
                $actualUsage = DB::table('promotion_redemptions')
                    ->where('promotion_id', $promotion->id)
                    ->count();

                if (
                    $promotion->usage_limit !== null
                    && $actualUsage > (int) $promotion->usage_limit
                ) {
                    throw new RuntimeException(
                        "Promotion usage limit exceeded: {$promotion->promotion_code}"
                    );
                }

                DB::table('promotions')
                    ->where('id', $promotion->id)
                    ->update([
                        'usage_count' => $actualUsage,
                        'updated_at' => now(),
                    ]);
            }

            $this->command->info(
                'Completed orders processed: ' . $orders->count()
            );

            $this->command->info(
                'Promotion redemptions created: ' . count($redemptionRows)
            );

            $this->command->info(
                'Orders without promotion: ' . ($orders->count() - count($redemptionRows))
            );

            $this->command->info(
                'Total discount applied: ₱' . number_format(
                    array_sum(array_column($planned, 'discount')),
                    2
                )
            );
        });
    }
}