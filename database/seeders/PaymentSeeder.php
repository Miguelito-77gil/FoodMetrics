<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $completedOrders = DB::table('orders')
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();

        $cancelledOrders = DB::table('orders')
            ->where('status', 'cancelled')
            ->orderBy('id')
            ->get();

        $cashiers = DB::table('users')
            ->where('role', 'cashier')
            ->where('is_active', true)
            ->pluck('id')
            ->values();

        if ($completedOrders->count() !== 135) {
            throw new RuntimeException('Expected exactly 135 completed orders.');
        }

        if ($cancelledOrders->count() !== 15) {
            throw new RuntimeException('Expected exactly 15 cancelled orders.');
        }

        if ($cashiers->count() !== 3) {
            throw new RuntimeException('Expected exactly 3 active cashiers.');
        }

        $methods = ['cash', 'gcash', 'bank_transfer', 'card'];
        $payments = [];
        $paymentSequence = 1;
        $splitOrders = 0;

        foreach ($completedOrders as $index => $order) {
            $amount = round((float) $order->total_amount, 2);
            $receivedBy = $order->user_id;

            // Every fifth completed order is paid in two installments.
            if ($index % 5 === 0) {
                $splitOrders++;

                $firstAmount = round($amount * 0.50, 2);
                $secondAmount = round($amount - $firstAmount, 2);

                $firstMethod = $methods[$index % count($methods)];
                $secondMethod = $methods[($index + 1) % count($methods)];

                $payments[] = [
                    'payment_number' => sprintf('PAY-%06d', $paymentSequence++),
                    'order_id' => $order->id,
                    'received_by' => $receivedBy,
                    'payment_method' => $firstMethod,
                    'amount' => $firstAmount,
                    'transaction_reference' => $firstMethod === 'cash'
                        ? null
                        : sprintf('SIM-%s-%06d', strtoupper($firstMethod), $paymentSequence),
                    'status' => 'completed',
                    'paid_at' => $order->ordered_at,
                    'notes' => 'Synthetic sample payment - first installment',
                    'created_at' => $order->ordered_at,
                    'updated_at' => $order->ordered_at,
                ];

                $payments[] = [
                    'payment_number' => sprintf('PAY-%06d', $paymentSequence++),
                    'order_id' => $order->id,
                    'received_by' => $receivedBy,
                    'payment_method' => $secondMethod,
                    'amount' => $secondAmount,
                    'transaction_reference' => $secondMethod === 'cash'
                        ? null
                        : sprintf('SIM-%s-%06d', strtoupper($secondMethod), $paymentSequence),
                    'status' => 'completed',
                    'paid_at' => $order->completed_at,
                    'notes' => 'Synthetic sample payment - final installment',
                    'created_at' => $order->completed_at,
                    'updated_at' => $order->completed_at,
                ];
            } else {
                $method = $methods[$index % count($methods)];

                $payments[] = [
                    'payment_number' => sprintf('PAY-%06d', $paymentSequence++),
                    'order_id' => $order->id,
                    'received_by' => $receivedBy,
                    'payment_method' => $method,
                    'amount' => $amount,
                    'transaction_reference' => $method === 'cash'
                        ? null
                        : sprintf('SIM-%s-%06d', strtoupper($method), $paymentSequence),
                    'status' => 'completed',
                    'paid_at' => $order->completed_at,
                    'notes' => 'Synthetic sample payment - fully paid',
                    'created_at' => $order->completed_at,
                    'updated_at' => $order->completed_at,
                ];
            }
        }

        // Three cancelled orders represent successful payments later refunded.
        foreach ($cancelledOrders->take(3) as $index => $order) {
            $method = $methods[($index + 1) % count($methods)];

            $payments[] = [
                'payment_number' => sprintf('PAY-%06d', $paymentSequence++),
                'order_id' => $order->id,
                'received_by' => $order->user_id,
                'payment_method' => $method,
                'amount' => round((float) $order->total_amount, 2),
                'transaction_reference' => $method === 'cash'
                    ? null
                    : sprintf('SIM-REFUND-%06d', $paymentSequence),
                'status' => 'refunded',
                'paid_at' => $order->ordered_at,
                'notes' => 'Synthetic sample payment - fully refunded after order cancellation',
                'created_at' => $order->ordered_at,
                'updated_at' => $order->cancelled_at,
            ];
        }

        DB::transaction(function () use ($payments) {
            // Safe rerun: replace payment records for the seeded orders.
            $orderIds = DB::table('orders')->pluck('id')->all();

            DB::table('payments')
                ->whereIn('order_id', $orderIds)
                ->delete();

            foreach (array_chunk($payments, 100) as $chunk) {
                DB::table('payments')->insert($chunk);
            }
        });

        $this->command->info('Completed orders: 135');
        $this->command->info('Split-payment orders: ' . $splitOrders);
        $this->command->info('Refunded cancelled orders: 3');
        $this->command->info(count($payments) . ' simulated payment records created successfully.');
    }
}