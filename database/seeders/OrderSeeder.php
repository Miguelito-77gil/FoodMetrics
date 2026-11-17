<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customerIds = Customer::where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $cashierIds = User::where('role', 'cashier')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $tableIds = RestaurantTable::where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($customerIds) < 10) {
            throw new RuntimeException('Insufficient active customers for order generation.');
        }

        if (count($cashierIds) < 1) {
            throw new RuntimeException('No active cashier accounts found.');
        }

        if (count($tableIds) < 1) {
            throw new RuntimeException('No active restaurant tables found.');
        }

        $distribution = [
            'dine_in' => [
                'completed' => 50,
                'cancelled' => 5,
            ],
            'takeout' => [
                'completed' => 45,
                'cancelled' => 5,
            ],
            'delivery' => [
                'completed' => 40,
                'cancelled' => 5,
            ],
        ];

        $baseDate = CarbonImmutable::create(2025, 11, 1, 10, 0, 0);

        DB::transaction(function () use (
            $customerIds,
            $cashierIds,
            $tableIds,
            $distribution,
            $baseDate
        ) {
            $sequence = 0;
            $typeOffset = 0;

            foreach ($distribution as $orderType => $statuses) {
                foreach ($statuses as $status => $count) {
                    for ($localIndex = 0; $localIndex < $count; $localIndex++) {
                        $sequence++;

                        // Deterministic distribution across the full historical year.
                        $orderedAt = $baseDate
                            ->addDays(($sequence * 37) % 365)
                            ->addMinutes(($sequence * 43) % 600);

                        $customerIndex = (
                            $sequence * 7 + $typeOffset
                        ) % count($customerIds);

                        $cashierIndex = (
                            $sequence * 5 + $typeOffset
                        ) % count($cashierIds);

                        $customerId = $customerIds[$customerIndex];
                        $cashierId = $cashierIds[$cashierIndex];

                        $tableId = $orderType === 'dine_in'
                            ? $tableIds[($sequence * 3) % count($tableIds)]
                            : null;

                        // Synthetic order amounts, expressed in Philippine pesos.
                        $subtotal = 350 + (($sequence * 173) % 3151);

                        // No promotion discounts are applied at this stage.
                        $discountAmount = 0.00;

                        // Synthetic modeled tax amount.
                        $taxAmount = round($subtotal * 0.12, 2);

                        $deliveryFee = $orderType === 'delivery'
                            ? 69 + (($sequence % 4) * 10)
                            : 0.00;

                        $totalAmount = round(
                            $subtotal - $discountAmount + $taxAmount + $deliveryFee,
                            2
                        );

                        $completedAt = $status === 'completed'
                            ? $orderedAt->addMinutes(25 + (($sequence * 17) % 66))
                            : null;

                        $cancelledAt = $status === 'cancelled'
                            ? $orderedAt->addMinutes(5 + (($sequence * 11) % 86))
                            : null;

                        $orderNumber = sprintf('ORD-%06d', $sequence);

                        $notes = match ($status) {
                            'cancelled' => 'Simulated historical cancellation record.',
                            'completed' => 'Simulated historical completed order.',
                            default => 'Simulated historical order.',
                        };

                        Order::updateOrCreate(
                            ['order_number' => $orderNumber],
                            [
                                'customer_id' => $customerId,
                                'user_id' => $cashierId,
                                'restaurant_table_id' => $tableId,
                                'order_type' => $orderType,
                                'status' => $status,
                                'subtotal' => $subtotal,
                                'discount_amount' => $discountAmount,
                                'tax_amount' => $taxAmount,
                                'delivery_fee' => $deliveryFee,
                                'total_amount' => $totalAmount,
                                'notes' => $notes,
                                'ordered_at' => $orderedAt->format('Y-m-d H:i:s'),
                                'completed_at' => $completedAt?->format('Y-m-d H:i:s'),
                                'cancelled_at' => $cancelledAt?->format('Y-m-d H:i:s'),
                                'created_at' => $orderedAt->format('Y-m-d H:i:s'),
                                'updated_at' => $orderedAt->format('Y-m-d H:i:s'),
                            ]
                        );
                    }
                }

                $typeOffset += 13;
            }
        });

        $this->command->info('150 simulated historical orders created or updated successfully.');
        $this->command->info('135 completed orders and 15 cancelled orders.');
    }
}