<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\Customer;
use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddOn;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class OrderCreationService
{
    public function create(array $data, User $cashier): Order
    {
        return DB::transaction(function () use ($data, $cashier) {
            $orderType = $data['order_type'];
            $customerId = $data['customer_id'] ?? null;

            if ($customerId !== null) {
                Customer::query()
                    ->whereKey($customerId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
            }


            $subtotalCents = 0;
            $preparedItems = [];

            foreach ($data['items'] as $index => $itemData) {
                $product = Product::query()
                    ->whereKey($itemData['product_id'])
                    ->where('is_available', true)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" =>
                            'The selected product is unavailable.',
                    ]);
                }

                $variant = null;

                if (!empty($itemData['product_variant_id'])) {
                    $variant = ProductVariant::query()
                        ->whereKey($itemData['product_variant_id'])
                        ->where('product_id', $product->id)
                        ->where('is_available', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_variant_id" =>
                                'The selected variant is invalid or unavailable.',
                        ]);
                    }
                }

                $quantity = (int) $itemData['quantity'];

                $unitPriceCents = $this->toCents($product->base_price);

                if ($variant) {
                    $unitPriceCents += $this->toCents(
                        $variant->price_adjustment
                    );
                }

                $preparedAddOns = [];
                $addOnUnitTotalCents = 0;

                foreach (($itemData['add_ons'] ?? []) as $addOnIndex => $addOnData) {
                    $addOn = $product->addOns()
                        ->where('add_ons.id', $addOnData['add_on_id'])
                        ->where('add_ons.is_available', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$addOn) {
                        throw ValidationException::withMessages([
                            "items.{$index}.add_ons.{$addOnIndex}.add_on_id" =>
                                'The selected add-on is invalid or unavailable for this product.',
                        ]);
                    }

                    $addOnQuantity = (int) $addOnData['quantity'];
                    $addOnPriceCents = $this->toCents($addOn->price);

                    $preparedAddOns[] = [
                        'model' => $addOn,
                        'quantity' => $addOnQuantity,
                        'unit_price_cents' => $addOnPriceCents,
                    ];

                    $addOnUnitTotalCents +=
                        $addOnPriceCents * $addOnQuantity;
                }

                $lineTotalCents =
                    ($unitPriceCents + $addOnUnitTotalCents) * $quantity;

                $subtotalCents += $lineTotalCents;

                $preparedItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'line_total_cents' => $lineTotalCents,
                    'notes' => $itemData['notes'] ?? null,
                    'add_ons' => $preparedAddOns,
                ];
            }

            $discountCents = 0;
            $promotion = null;

            if (!empty($data['promotion_code'])) {
                $promotion = Promotion::query()
                    ->where('promotion_code', strtoupper(trim($data['promotion_code'])))
                    ->lockForUpdate()
                    ->first();

                $today = now()->toDateString();

                $validPromotion = $promotion
                    && $promotion->status === 'active'
                    && $promotion->start_date->toDateString() <= $today
                    && (
                        $promotion->end_date === null ||
                        $promotion->end_date->toDateString() >= $today
                    )
                    && (
                        $promotion->usage_limit === null ||
                        $promotion->usage_count < $promotion->usage_limit
                    )
                    && $subtotalCents >= $this->toCents(
                        $promotion->minimum_order_amount
                    );

                if (!$validPromotion) {
                    throw ValidationException::withMessages([
                        'promotion_code' =>
                            'The promotion code is invalid, expired, unavailable, or does not meet the minimum order amount.',
                    ]);
                }

                if ($promotion->discount_type === 'percentage') {
                    $discountCents = (int) round(
                        $subtotalCents *
                        ((float) $promotion->discount_value / 100)
                    );
                } else {
                    $discountCents = $this->toCents(
                        $promotion->discount_value
                    );
                }

                if ($promotion->maximum_discount_amount !== null) {
                    $discountCents = min(
                        $discountCents,
                        $this->toCents($promotion->maximum_discount_amount)
                    );
                }

                $discountCents = min($discountCents, $subtotalCents);
            }

            $taxCents = 0;
            $deliveryFeeCents = 0;

            $totalCents = max(
                0,
                $subtotalCents - $discountCents +
                $taxCents + $deliveryFeeCents
            );

            $order = Order::create([
                'order_number' => 'TMP-' . Str::uuid(),
                'customer_id' => $customerId,
                'user_id' => $cashier->id,
                'restaurant_table_id' => null,
                'order_type' => $orderType,
                'status' => 'pending',
                'subtotal' => $this->toAmount($subtotalCents),
                'discount_amount' => $this->toAmount($discountCents),
                'tax_amount' => $this->toAmount($taxCents),
                'delivery_fee' => $this->toAmount($deliveryFeeCents),
                'total_amount' => $this->toAmount($totalCents),
                'notes' => $data['notes'] ?? null,
                'ordered_at' => now(),
            ]);

            $order->update([
                'order_number' => 'ORD-' . str_pad(
                    (string) $order->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            foreach ($preparedItems as $preparedItem) {
                $product = $preparedItem['product'];
                $variant = $preparedItem['variant'];

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $this->toAmount(
                        $preparedItem['unit_price_cents']
                    ),
                    'subtotal' => $this->toAmount(
                        $preparedItem['line_total_cents']
                    ),
                    'notes' => $preparedItem['notes'],
                ]);

                foreach ($preparedItem['add_ons'] as $preparedAddOn) {
                    $addOn = $preparedAddOn['model'];
                    $addOnQuantity = $preparedAddOn['quantity'];
                    $addOnPriceCents = $preparedAddOn['unit_price_cents'];

                    OrderItemAddOn::create([
                        'order_item_id' => $orderItem->id,
                        'add_on_id' => $addOn->id,
                        'add_on_name' => $addOn->name,
                        'quantity' => $addOnQuantity,
                        'unit_price' => $this->toAmount($addOnPriceCents),
                        'subtotal' => $this->toAmount(
                            $addOnPriceCents *
                            $addOnQuantity *
                            $preparedItem['quantity']
                        ),
                    ]);
                }
            }

            if ($promotion) {
                PromotionRedemption::create([
                    'promotion_id' => $promotion->id,
                    'order_id' => $order->id,
                    'customer_id' => $customerId,
                    'discount_amount' => $this->toAmount($discountCents),
                    'redeemed_at' => now(),
                ]);

                $promotion->increment('usage_count');
            }

            if ($orderType === 'delivery') {
                DeliveryDetail::create([
                    'order_id' => $order->id,
                    'delivery_staff_id' => null,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'delivery_address' => $data['delivery_address'],
                    'delivery_instructions' =>
                        $data['delivery_instructions'] ?? null,
                    'delivery_fee' => '0.00',
                    'status' => 'pending',
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'updated_by' => $cashier->id,
                'previous_status' => null,
                'new_status' => 'pending',
                'remarks' => 'Order created.',
                'changed_at' => now(),
            ]);

            return $order->fresh([
                'orderItems.addOns',
                'deliveryDetail',
                'statusHistory',
            ]);
        }, 3);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function toAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}