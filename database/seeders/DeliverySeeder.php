<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $orders = DB::table('orders')
            ->where('order_type', 'delivery')
            ->orderBy('id')
            ->get();

        $staff = DB::table('users')
            ->where('role', 'delivery_staff')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->values();

        if ($orders->count() !== 45) {
            throw new RuntimeException('Expected exactly 45 delivery orders.');
        }

        if ($orders->where('status', 'completed')->count() !== 40) {
            throw new RuntimeException('Expected exactly 40 completed delivery orders.');
        }

        if ($orders->where('status', 'cancelled')->count() !== 5) {
            throw new RuntimeException('Expected exactly 5 cancelled delivery orders.');
        }

        if ($staff->count() !== 3) {
            throw new RuntimeException('Expected exactly 3 active delivery staff.');
        }

        $deliveryRows = [];
        $historyRows = [];

        foreach ($orders as $index => $order) {
            $customer = DB::table('customers')
                ->where('id', $order->customer_id)
                ->first();

            if (!$customer) {
                throw new RuntimeException("Customer missing for {$order->order_number}.");
            }

            if (empty($customer->address) || empty($customer->phone)) {
                throw new RuntimeException("Customer address or phone missing for {$order->order_number}.");
            }

            $recipientName = trim($customer->first_name . ' ' . $customer->last_name);

            if ($recipientName === '') {
                throw new RuntimeException("Customer name missing for {$order->order_number}.");
            }

            $orderedAt = Carbon::parse($order->ordered_at);

            $isCancelled = $order->status === 'cancelled';

            $deliveryStaffId = $isCancelled
                ? null
                : $staff[$index % $staff->count()];

            $assignedAt = null;
            $pickedUpAt = null;
            $deliveredAt = null;

            if (!$isCancelled) {
                $completedAt = Carbon::parse($order->completed_at);

                // Keep delivery events chronologically ordered.
                $duration = max(3600, $completedAt->timestamp - $orderedAt->timestamp);

                $assignedAt = $orderedAt->copy()->addSeconds(
                    (int) min(300, floor($duration * 0.15))
                );

                $pickedUpAt = $orderedAt->copy()->addSeconds(
                    (int) min(1200, floor($duration * 0.40))
                );

                $deliveredAt = $completedAt;

                if ($pickedUpAt->greaterThanOrEqualTo($deliveredAt)) {
                    $pickedUpAt = $deliveredAt->copy()->subMinutes(5);
                }

                if ($assignedAt->greaterThanOrEqualTo($pickedUpAt)) {
                    $assignedAt = $pickedUpAt->copy()->subMinutes(5);
                }
            }

            $deliveryStatus = $isCancelled ? 'cancelled' : 'delivered';

            $deliveryRows[] = [
                'order_id' => $order->id,
                'delivery_staff_id' => $deliveryStaffId,
                'recipient_name' => $recipientName,
                'recipient_phone' => $customer->phone,
                'delivery_address' => $customer->address,
                'delivery_instructions' => $isCancelled
                    ? 'Synthetic sample delivery cancelled before dispatch.'
                    : 'Synthetic sample delivery instructions: contact recipient upon arrival.',
                'delivery_fee' => $order->delivery_fee,
                'status' => $deliveryStatus,
                'assigned_at' => $assignedAt?->toDateTimeString(),
                'picked_up_at' => $pickedUpAt?->toDateTimeString(),
                'delivered_at' => $deliveredAt?->toDateTimeString(),
                'failure_reason' => null,
                'created_at' => $order->ordered_at,
                'updated_at' => $isCancelled
                    ? $order->cancelled_at
                    : $order->completed_at,
            ];
        }

        DB::transaction(function () use ($orders, $deliveryRows, &$historyRows, $staff) {
            $orderIds = $orders->pluck('id')->all();

            $existingDeliveryIds = DB::table('delivery_details')
                ->whereIn('order_id', $orderIds)
                ->pluck('id')
                ->all();

            if (!empty($existingDeliveryIds)) {
                DB::table('delivery_status_history')
                    ->whereIn('delivery_detail_id', $existingDeliveryIds)
                    ->delete();
            }

            DB::table('delivery_details')
                ->whereIn('order_id', $orderIds)
                ->delete();

            foreach ($deliveryRows as $row) {
                DB::table('delivery_details')->insert($row);
            }

            $details = DB::table('delivery_details')
                ->whereIn('order_id', $orderIds)
                ->get();

            $detailsByOrder = $details->keyBy('order_id');

            foreach ($orders as $index => $order) {
                $detail = $detailsByOrder[$order->id] ?? null;

                if (!$detail) {
                    throw new RuntimeException("Delivery detail was not created for {$order->order_number}.");
                }

                $orderedAt = Carbon::parse($order->ordered_at);

                $historyRows[] = [
                    'delivery_detail_id' => $detail->id,
                    'updated_by' => $order->user_id,
                    'previous_status' => null,
                    'new_status' => 'pending',
                    'remarks' => 'Delivery request recorded.',
                    'changed_at' => $orderedAt->toDateTimeString(),
                    'created_at' => $orderedAt->toDateTimeString(),
                    'updated_at' => $orderedAt->toDateTimeString(),
                ];

                if ($order->status === 'cancelled') {
                    $cancelledAt = Carbon::parse($order->cancelled_at);

                    $historyRows[] = [
                        'delivery_detail_id' => $detail->id,
                        'updated_by' => $order->user_id,
                        'previous_status' => 'pending',
                        'new_status' => 'cancelled',
                        'remarks' => 'Delivery request cancelled before dispatch.',
                        'changed_at' => $cancelledAt->toDateTimeString(),
                        'created_at' => $cancelledAt->toDateTimeString(),
                        'updated_at' => $cancelledAt->toDateTimeString(),
                    ];

                    continue;
                }

                $staffId = $detail->delivery_staff_id;
                $assignedAt = Carbon::parse($detail->assigned_at);
                $pickedUpAt = Carbon::parse($detail->picked_up_at);
                $deliveredAt = Carbon::parse($detail->delivered_at);

                $events = [
                    [
                        'previous_status' => 'pending',
                        'new_status' => 'assigned',
                        'remarks' => 'Delivery staff assigned.',
                        'changed_at' => $assignedAt,
                    ],
                    [
                        'previous_status' => 'assigned',
                        'new_status' => 'picked_up',
                        'remarks' => 'Order picked up by delivery staff.',
                        'changed_at' => $pickedUpAt,
                    ],
                    [
                        'previous_status' => 'picked_up',
                        'new_status' => 'delivered',
                        'remarks' => 'Order successfully delivered to recipient.',
                        'changed_at' => $deliveredAt,
                    ],
                ];

                foreach ($events as $event) {
                    $timestamp = $event['changed_at']->toDateTimeString();

                    $historyRows[] = [
                        'delivery_detail_id' => $detail->id,
                        'updated_by' => $staffId,
                        'previous_status' => $event['previous_status'],
                        'new_status' => $event['new_status'],
                        'remarks' => $event['remarks'],
                        'changed_at' => $timestamp,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            foreach (array_chunk($historyRows, 100) as $chunk) {
                DB::table('delivery_status_history')->insert($chunk);
            }
        });

        $this->command->info('Completed deliveries: 40');
        $this->command->info('Cancelled deliveries: 5');
        $this->command->info('Assigned delivery staff: ' . $staff->count());
        $this->command->info('Delivery status history records: ' . count($historyRows));
        $this->command->info('45 simulated delivery records created successfully.');
    }
}