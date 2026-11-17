<?php

namespace App\Http\Controllers;

use App\Models\DeliveryDetail;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\InventoryConsumptionService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DeliveryDetail::class);

        $user = request()->user();

        $query = DeliveryDetail::query()
            ->with(['order.customer', 'deliveryStaff'])
            ->latest();

        if ($user->role !== 'admin') {
            $query->where('delivery_staff_id', $user->id);
        }

        return view('delivery.index', [
            'deliveries' => $query->paginate(15),
        ]);
    }

    public function show(DeliveryDetail $delivery): View
    {
        Gate::authorize('view', $delivery);

        $delivery->load([
            'order.customer',
            'deliveryStaff',
            'statusHistory.updatedBy',
        ]);

        return view('delivery.show', [
            'delivery' => $delivery,
        ]);
    }

    public function assignmentIndex(): View
    {
        Gate::authorize('assign', DeliveryDetail::class);

        $deliveries = DeliveryDetail::query()
            ->with(['order.customer'])
            ->where('status', 'pending')
            ->whereNull('delivery_staff_id')
            ->oldest()
            ->paginate(15);

        $deliveryStaff = User::query()
            ->where('role', 'delivery_staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.deliveries.index', [
            'deliveries' => $deliveries,
            'deliveryStaff' => $deliveryStaff,
        ]);
    }

    public function assign(
        Request $request,
        DeliveryDetail $delivery
    ): RedirectResponse {
        Gate::authorize('assign', DeliveryDetail::class);

        $validated = $request->validate([
            'delivery_staff_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('role', 'delivery_staff')
                        ->where('is_active', true)
                ),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use ($request, $delivery, $validated) {
            $lockedDelivery = DeliveryDetail::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            abort_unless(
                $lockedDelivery->status === 'pending'
                    && $lockedDelivery->delivery_staff_id === null,
                422,
                'Only pending and unassigned deliveries can be assigned.'
            );

            $now = now();

            $lockedDelivery->update([
                'delivery_staff_id' => $validated['delivery_staff_id'],
                'status' => 'assigned',
                'assigned_at' => $now,
            ]);

            DeliveryStatusHistory::create([
                'delivery_detail_id' => $lockedDelivery->id,
                'updated_by' => $request->user()->id,
                'previous_status' => 'pending',
                'new_status' => 'assigned',
                'remarks' => $validated['remarks'] ?? null,
                'changed_at' => $now,
            ]);
        });

        return redirect()
            ->route('delivery.show', $delivery)
            ->with('success', 'Delivery assigned successfully.');
    }

    public function updateStatus(
        Request $request,
        DeliveryDetail $delivery
    ): RedirectResponse {
        Gate::authorize('update', $delivery);

        $allowedStatuses = match ($delivery->status) {
            'assigned' => ['picked_up'],
            'picked_up' => ['delivered', 'failed'],
            default => [],
        };

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in($allowedStatuses),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'failure_reason' => [
                'required_if:status,failed',
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use ($request, $delivery, $validated) {
            $lockedDelivery = DeliveryDetail::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            Gate::authorize('update', $lockedDelivery);

            $currentStatus = $lockedDelivery->status;

            $allowed = match ($currentStatus) {
                'assigned' => ['picked_up'],
                'picked_up' => ['delivered', 'failed'],
                default => [],
            };

            abort_unless(
                in_array($validated['status'], $allowed, true),
                422,
                'This delivery status transition is not allowed.'
            );

            $newStatus = $validated['status'];
            $now = now();

            $updates = [
                'status' => $newStatus,
            ];

            if ($newStatus === 'picked_up') {
                $updates['picked_up_at'] = $now;
            }

            if ($newStatus === 'delivered') {
                $updates['delivered_at'] = $now;
            }

            if ($newStatus === 'failed') {
                $updates['failure_reason'] = $validated['failure_reason'];
            }

            $lockedDelivery->update($updates);

            if (in_array($newStatus, ['picked_up', 'delivered'], true)) {
                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedDelivery->order_id);

                abort_unless(
                    $order->order_type === 'delivery',
                    422,
                    'This delivery must belong to a delivery order.'
                );

                $previousOrderStatus = $order->status;

                if ($newStatus === 'picked_up') {
                    abort_unless(
                        $previousOrderStatus === 'ready',
                        422,
                        'Only ready orders can be picked up for delivery.'
                    );

                    $order->update([
                        'status' => 'out_for_delivery',
                    ]);

                    $newOrderStatus = 'out_for_delivery';
                } else {
                    abort_unless(
                        $previousOrderStatus === 'out_for_delivery',
                        422,
                        'Only orders currently out for delivery can be completed.'
                    );

                    $order->update([
                        'status' => 'completed',
                        'completed_at' => $now,
                    ]);

                    $newOrderStatus = 'completed';

                    app(InventoryConsumptionService::class)->consumeForOrder(
                        $order->id,
                        $request->user()->id
                    );
                }

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'updated_by' => $request->user()->id,
                    'previous_status' => $previousOrderStatus,
                    'new_status' => $newOrderStatus,
                    'remarks' => $validated['remarks'] ?? null,
                    'changed_at' => $now,
                ]);
            }

            DeliveryStatusHistory::create([
                'delivery_detail_id' => $lockedDelivery->id,
                'updated_by' => $request->user()->id,
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'remarks' => $validated['remarks'] ?? null,
                'changed_at' => $now,
            ]);
        });

        return redirect()
            ->route('delivery.show', $delivery)
            ->with('success', 'Delivery status updated successfully.');
    }
}