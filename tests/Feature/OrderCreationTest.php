<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCashier(): User
    {
        return User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    private function makeProduct(float $price = 100): Product
    {
        $suffix = Str::lower(Str::random(10));

        $category = Category::create([
            'name' => 'Order Test Category ' . $suffix,
            'slug' => 'order-test-category-' . $suffix,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'product_code' => 'OT-' . strtoupper(Str::random(8)),
            'name' => 'Order Test Product',
            'slug' => 'order-test-product-' . $suffix,
            'base_price' => $price,
            'is_available' => true,
            'is_featured' => false,
        ]);
    }

    private function makeAddOn(float $price = 10): AddOn
    {
        return AddOn::create([
            'name' => 'Test Add-on ' . Str::random(8),
            'price' => $price,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function basicPayload(Product $product): array
    {
        return [
            'order_type' => 'takeout',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];
    }

    public function test_cashier_can_create_takeout_order_with_server_calculated_prices(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct(100);

        $payload = $this->basicPayload($product);

        // These untrusted client values must not affect server pricing.
        $payload['items'][0]['unit_price'] = 1;
        $payload['items'][0]['subtotal'] = 1;

        $response = $this->actingAs($cashier)
            ->post(route('cashier.orders.store'), $payload);

        $order = Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success', 'Order created successfully.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_number' => 'ORD-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => 'pending',
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'delivery_fee' => '0.00',
            'total_amount' => '100.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'updated_by' => $cashier->id,
            'previous_status' => null,
            'new_status' => 'pending',
            'remarks' => 'Order created.',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_status_history', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cashier_can_create_dine_in_order_without_selecting_a_table(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct(150);

        $payload = $this->basicPayload($product);
        $payload['order_type'] = 'dine_in';

        $response = $this->actingAs($cashier)
            ->post(route('cashier.orders.store'), $payload);

        $order = Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_type' => 'dine_in',
            'restaurant_table_id' => null,
            'status' => 'pending',
            'total_amount' => '150.00',
        ]);
    }

    public function test_cashier_can_create_delivery_order_with_pending_unassigned_delivery(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct(200);

        $payload = $this->basicPayload($product);
        $payload['order_type'] = 'delivery';
        $payload['recipient_name'] = 'Test Recipient';
        $payload['recipient_phone'] = '09123456789';
        $payload['delivery_address'] = 'Test Delivery Address';
        $payload['delivery_instructions'] = 'Please call upon arrival';

        $response = $this->actingAs($cashier)
            ->post(route('cashier.orders.store'), $payload);

        $order = Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('delivery_details', [
            'order_id' => $order->id,
            'delivery_staff_id' => null,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '09123456789',
            'delivery_address' => 'Test Delivery Address',
            'delivery_instructions' => 'Please call upon arrival',
            'delivery_fee' => '0.00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_type' => 'delivery',
            'delivery_fee' => '0.00',
            'total_amount' => '200.00',
        ]);
    }

    public function test_variant_add_ons_and_promotion_are_calculated_from_database_prices(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct(100);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Large',
            'price_adjustment' => 25,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $addOn = $this->makeAddOn(10);
        $product->addOns()->attach($addOn->id);

        $promotion = Promotion::create([
            'promotion_name' => 'Test Ten Percent',
            'promotion_code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount' => 100,
            'maximum_discount_amount' => 15,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'usage_limit' => 5,
            'usage_count' => 0,
            'status' => 'active',
            'description' => 'Order creation test promotion',
            'created_by' => $cashier->id,
        ]);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.orders.store'), [
                'order_type' => 'takeout',
                'promotion_code' => ' test10 ',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                        'add_ons' => [
                            [
                                'add_on_id' => $addOn->id,
                                'quantity' => 2,
                            ],
                        ],
                    ],
                ],
            ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('orders.show', $order));

        // (â‚±100 + â‚±25 + (â‚±10 Ã— 2)) Ã— 2 = â‚±290.
        // 10% discount is â‚±29, capped at â‚±15.
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'subtotal' => '290.00',
            'discount_amount' => '15.00',
            'total_amount' => '275.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Large',
            'quantity' => 2,
            'unit_price' => '125.00',
            'subtotal' => '290.00',
        ]);

        $this->assertDatabaseHas('order_item_add_ons', [
            'add_on_id' => $addOn->id,
            'quantity' => 2,
            'unit_price' => '10.00',
            'subtotal' => '40.00',
        ]);

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id' => $promotion->id,
            'order_id' => $order->id,
            'discount_amount' => '15.00',
        ]);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'usage_count' => 1,
        ]);
    }


    public function test_same_add_on_can_be_selected_for_different_products(): void
    {
        $cashier = $this->makeCashier();
        $firstProduct = $this->makeProduct();
        $secondProduct = $this->makeProduct();
        $addOn = $this->makeAddOn(10);

        $firstProduct->addOns()->attach($addOn->id);
        $secondProduct->addOns()->attach($addOn->id);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.orders.store'), [
                'order_type' => 'takeout',
                'items' => [
                    [
                        'product_id' => $firstProduct->id,
                        'quantity' => 1,
                        'add_ons' => [
                            [
                                'add_on_id' => $addOn->id,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 1,
                        'add_ons' => [
                            [
                                'add_on_id' => $addOn->id,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('order_item_add_ons', 2);
    }

    public function test_same_add_on_cannot_be_selected_twice_for_one_product(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct();
        $addOn = $this->makeAddOn();

        $product->addOns()->attach($addOn->id);

        $this->actingAs($cashier)
            ->from('/cashier')
            ->post(route('cashier.orders.store'), [
                'order_type' => 'takeout',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'add_ons' => [
                            [
                                'add_on_id' => $addOn->id,
                                'quantity' => 1,
                            ],
                            [
                                'add_on_id' => $addOn->id,
                                'quantity' => 2,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.add_ons');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_item_add_ons', 0);
    }
    public function test_invalid_add_on_rolls_back_the_entire_order_creation(): void
    {
        $cashier = $this->makeCashier();
        $product = $this->makeProduct();

        $unrelatedAddOn = $this->makeAddOn(20);

        // This add-on exists, but is not linked to the selected product.
        $this->assertDatabaseHas('add_ons', [
            'id' => $unrelatedAddOn->id,
        ]);

        $this->actingAs($cashier)
            ->from('/cashier')
            ->post(route('cashier.orders.store'), [
                'order_type' => 'takeout',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'add_ons' => [
                            [
                                'add_on_id' => $unrelatedAddOn->id,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.add_ons.0.add_on_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_item_add_ons', 0);
        $this->assertDatabaseCount('order_status_history', 0);
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }

    public function test_guest_cannot_create_an_order(): void
    {
        $product = $this->makeProduct();

        $this->post(route('cashier.orders.store'), $this->basicPayload($product))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('orders', 0);
    }
}