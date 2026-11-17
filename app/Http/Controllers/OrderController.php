<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\InventoryConsumptionService;
use App\Services\OrderCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function store(
        Request $request,
        OrderCreationService $orderCreationService
    ): RedirectResponse {
        $validated = $request->validate([
            'order_type' => [
                'required',
                'string',
                Rule::in(['dine_in', 'takeout', 'delivery']),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:99',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'items.*.add_ons' => [
                'nullable',
                'array',
                'max:20',
                function ($attribute, $value, $fail) {
                    if (!is_array($value)) {
                        return;
                    }

                    $seen = [];

                    foreach ($value as $addOn) {
                        if (!is_array($addOn) || !array_key_exists('add_on_id', $addOn)) {
                            continue;
                        }

                        $id = (string) $addOn['add_on_id'];

                        if (isset($seen[$id])) {
                            $fail('An add-on may only be selected once per product.');
                            return;
                        }

                        $seen[$id] = true;
                    }
                },
            ],
'items.*.add_ons.*.add_on_id' => [
                'required',
                'integer',
                'exists:add_ons,id',
            ],
            'items.*.add_ons.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],
            'promotion_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'recipient_name' => [
                'required_if:order_type,delivery',
                'nullable',
                'string',
                'max:150',
            ],
            'recipient_phone' => [
                'required_if:order_type,delivery',
                'nullable',
                'string',
                'max:30',
            ],
            'delivery_address' => [
                'required_if:order_type,delivery',
                'nullable',
                'string',
                'max:2000',
            ],
            'delivery_instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $order = $orderCreationService->create(
            $validated,
            $request->user()
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order created successfully.');
    }
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('updateStatus', $order);

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['confirmed', 'preparing', 'ready']),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($order, $request, $validated) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            Gate::authorize('updateStatus', $lockedOrder);

            $allowedTransitions = [
                'pending' => 'confirmed',
                'confirmed' => 'preparing',
                'preparing' => 'ready',
            ];

            $expectedStatus = $allowedTransitions[$lockedOrder->status] ?? null;

            abort_unless(
                $expectedStatus === $validated['status'],
                422,
                'Invalid order status transition.'
            );

            $now = now();
            $previousStatus = $lockedOrder->status;
            $updatedBy = $request->user()->id;

            $lockedOrder->update([
                'status' => $validated['status'],
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'updated_by' => $updatedBy,
                'previous_status' => $previousStatus,
                'new_status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
                'changed_at' => $now,
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with('success', 'Order status updated successfully.');
    }

    public function complete(Order $order): RedirectResponse
    {
        Gate::authorize('complete', $order);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            Gate::authorize('complete', $lockedOrder);

            abort_unless(
                in_array($lockedOrder->order_type, ['dine_in', 'takeout'], true),
                422,
                'Only dine-in and takeout orders can be completed here.'
            );

            abort_unless(
                $lockedOrder->status === 'ready',
                422,
                'Only ready orders can be completed.'
            );

            $now = now();
            $previousStatus = $lockedOrder->status;
            $updatedBy = request()->user()->id;

            $lockedOrder->update([
                'status' => 'completed',
                'completed_at' => $now,
            ]);

            app(InventoryConsumptionService::class)->consumeForOrder(
                $lockedOrder->id,
                $updatedBy
            );

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'updated_by' => $updatedBy,
                'previous_status' => $previousStatus,
                'new_status' => 'completed',
                'remarks' => 'Order completed successfully.',
                'changed_at' => $now,
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with('success', 'Order completed and inventory deducted successfully.');
    }

    public function create()
    {
        $categories = \App\Models\Category::query()
            ->where('is_active', true)
            ->whereHas('products', function ($query) {
                $query->where('is_available', true);
            })
            ->with([
                'products' => function ($query) {
                    $query->where('is_available', true)
                        ->with([
                            'variants' => function ($variantQuery) {
                                $variantQuery->where('is_available', true)
                                    ->orderBy('sort_order');
                            },
                            'addOns' => function ($addOnQuery) {
                                $addOnQuery->where('is_available', true)
                                    ->orderBy('sort_order');
                            },
                        ])
                        ->orderBy('name');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $customers = \App\Models\Customer::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $promotions = \App\Models\Promotion::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('promotion_name')
            ->get();


        return view('orders.create', compact(
            'categories',
            'customers',
            'promotions',
        ));
    }
    public function index()
    {
        $filters = request()->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,preparing,ready,out_for_delivery,completed,cancelled'],
            'type' => ['nullable', 'in:dine_in,takeout,delivery'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Order::query()
            ->with(['customer', 'user']);

        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($orderQuery) use ($search) {
                $orderQuery
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where(function ($nameQuery) use ($search) {
                            $nameQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('order_type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('ordered_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('ordered_at', '<=', $filters['date_to']);
        }

        $orders = $query
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totalOrders = Order::count();

        return view('orders.index', compact(
            'orders',
            'filters',
            'totalOrders'
        ));
    }
    public function show(Order $order)
    {
        abort_unless(
            in_array(request()->user()->role, ['admin', 'cashier'], true),
            403
        );

        $order->load([
            'customer',
            'user',
            'restaurantTable',
            'orderItems.addOns',
            'payments.receivedBy',
            'deliveryDetail.deliveryStaff',
            'deliveryDetail.statusHistory.updatedBy',
            'statusHistory.updatedBy',
        ]);

        $totalAmountCents = (int) round((float) $order->total_amount * 100);

        $paidAmountCents = (int) $order->payments
            ->where('status', 'completed')
            ->sum(fn ($payment) => (int) round((float) $payment->amount * 100));

        $remainingBalanceCents = max(
            0,
            $totalAmountCents - $paidAmountCents
        );

        $paymentStatus = match (true) {
            $paidAmountCents <= 0 => 'unpaid',
            $remainingBalanceCents <= 0 => 'paid',
            default => 'partially_paid',
        };

        return view('orders.show', compact(
            'order',
            'totalAmountCents',
            'paidAmountCents',
            'remainingBalanceCents',
            'paymentStatus'
        ));
    }
}