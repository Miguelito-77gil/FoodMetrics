@extends('layouts.app')

@section('title', 'Order Details')
@section('breadcrumb', 'Order Details')

@push('head')
<style>
    .order-detail-page { width: min(100% - 48px, 1280px); margin: 0 auto; padding: 30px 0 48px; }
    .order-detail-header { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap; margin-bottom:24px; }
    .order-detail-header h1 { margin:0; font-size:clamp(24px,3vw,31px); font-weight:750; letter-spacing:-.035em; }
    .order-detail-subtitle { margin-top:7px; color:var(--fm-muted); font-size:13px; }
    .order-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; margin-bottom:18px; }
    .order-detail-card { min-width:0; padding:22px; border:1px solid var(--fm-border); border-radius:var(--fm-radius); background:var(--fm-surface); margin-bottom:18px; }
    .order-detail-card h2 { margin:0 0 17px; font-size:17px; font-weight:700; }
    .order-detail-card h3 { margin:20px 0 12px; font-size:14px; font-weight:700; }
    .order-detail-field { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; padding:11px 0; border-bottom:1px solid var(--fm-border); font-size:13px; }
    .order-detail-field:last-child { border-bottom:0; }
    .order-detail-field > span:first-child { color:var(--fm-muted); flex:0 0 42%; }
    .order-detail-field > strong { text-align:right; overflow-wrap:anywhere; }
    .order-detail-muted { color:var(--fm-muted); font-size:12px; line-height:1.6; }
    .order-detail-empty { padding:15px; border:1px dashed var(--fm-border); border-radius:9px; background:var(--fm-surface-soft); color:var(--fm-muted); font-size:13px; }
    .order-detail-table-wrap { width:100%; overflow-x:auto; border:1px solid var(--fm-border); border-radius:10px; }
    .order-detail-table { width:100%; min-width:540px; border-collapse:collapse; font-size:13px; text-align:left; }
    .order-detail-table th,.order-detail-table td { padding:13px 14px; border-bottom:1px solid var(--fm-border); vertical-align:top; }
    .order-detail-table th { background:var(--fm-surface-soft); color:var(--fm-muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
    .order-detail-table tbody tr:last-child td { border-bottom:0; }
    .order-detail-table .numeric { text-align:right; white-space:nowrap; }
    .order-detail-total { color:var(--fm-primary); font-size:17px; }
    .order-detail-payment-box { margin:18px 0; padding:17px; border:1px solid var(--fm-border); border-radius:10px; background:var(--fm-surface-soft); }
    .order-detail-payment-box h3 { margin:0 0 15px; }
    .order-detail-form-group { display:grid; gap:7px; margin-bottom:15px; }
    .order-detail-form-group label { font-size:13px; font-weight:650; }
    .order-detail-form-group input,.order-detail-form-group select,.order-detail-form-group textarea { width:100%; min-height:42px; padding:10px 12px; border:1px solid var(--fm-border); border-radius:8px; background:var(--fm-surface); color:var(--fm-text); font:inherit; font-size:13px; }
    .order-detail-form-group textarea { min-height:90px; resize:vertical; }
    .order-detail-form-group input:focus,.order-detail-form-group select:focus,.order-detail-form-group textarea:focus { outline:none; border-color:var(--fm-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--fm-primary) 16%,transparent); }
    .order-detail-error { color:var(--fm-danger); font-size:12px; }
    .order-detail-timeline { margin-left:7px; padding-left:22px; border-left:2px solid var(--fm-primary); }
    .order-detail-timeline-item { position:relative; padding:0 0 23px; }
    .order-detail-timeline-item:last-child { padding-bottom:0; }
    .order-detail-timeline-item::before { content:""; position:absolute; left:-28px; top:3px; width:10px; height:10px; border:2px solid var(--fm-surface); border-radius:50%; background:var(--fm-primary); box-shadow:0 0 0 1px var(--fm-primary); }
    .order-detail-transition { font-size:13px; font-weight:700; }
    .order-detail-remarks { margin-top:8px; color:var(--fm-text); font-size:13px; overflow-wrap:anywhere; }
    .order-detail-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:15px; }
    .order-detail-actions .fm-btn { min-width:145px; }
    .order-detail-modal-backdrop { display:none; position:fixed; inset:0; z-index:2000; align-items:center; justify-content:center; padding:20px; background:rgba(20,25,22,.58); }
    .order-detail-modal-backdrop.open { display:flex; }
    .order-detail-modal { width:100%; max-width:440px; padding:25px; border:1px solid var(--fm-border); border-radius:14px; background:var(--fm-surface); box-shadow:0 18px 55px rgba(0,0,0,.2); }
    .order-detail-modal h2 { margin:0 0 10px; font-size:19px; }
    .order-detail-modal p { color:var(--fm-muted); font-size:13px; line-height:1.7; }
    .order-detail-modal-buttons { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:9px; margin-top:22px; }
    .order-detail-back { flex-shrink:0; }
    @media(max-width:760px) {
        .order-detail-page { width:min(100% - 32px,1280px); padding:22px 0 35px; }
        .order-detail-grid { grid-template-columns:1fr; gap:0; }
        .order-detail-card { padding:17px; }
        .order-detail-header { flex-direction:column; align-items:stretch; }
        .order-detail-back { width:100%; }
        .order-detail-actions .fm-btn { flex:1 1 100%; }
    }
</style>
@endpush

@section('content')
@php
    $statusLabel = ucfirst(str_replace('_', ' ', $order->status));
    $statusBadge = match($order->status) {
        'completed', 'delivered' => 'fm-badge-success',
        'pending', 'confirmed', 'ready' => 'fm-badge-warning',
        'cancelled', 'failed' => 'fm-badge-danger',
        default => '',
    };

    $paymentBadge = match($paymentStatus) {
        'paid' => 'fm-badge-success',
        'partially_paid' => 'fm-badge-warning',
        default => 'fm-badge-danger',
    };
@endphp

<div class="order-detail-page">
    <section class="order-detail-header">
        <div>
            <h1>Order Details</h1>
            <p class="order-detail-subtitle">
                {{ $order->order_number }} ·
                Ordered {{ $order->ordered_at?->format('M d, Y h:i A') ?? 'N/A' }}
            </p>
        </div>
        <a href="{{ url()->previous() }}" class="fm-btn fm-btn-secondary order-detail-back">Back</a>
    </section>


    <section class="order-detail-grid">
        <article class="order-detail-card">
            <h2>Order Summary</h2>
            <div class="order-detail-field"><span>Order Number</span><strong>{{ $order->order_number }}</strong></div>
            <div class="order-detail-field"><span>Order Type</span><strong>{{ ucfirst(str_replace('_', ' ', $order->order_type)) }}</strong></div>
            <div class="order-detail-field">
                <span>Current Status</span>
                <strong><span class="fm-badge {{ $statusBadge }}">{{ $statusLabel }}</span></strong>
            </div>
            <div class="order-detail-field"><span>Processed By</span><strong>{{ $order->user?->name ?? 'N/A' }}</strong></div>
            <div class="order-detail-field"><span>Restaurant Table</span><strong>{{ $order->restaurantTable?->table_number ?? 'N/A' }}</strong></div>
            <div class="order-detail-field"><span>Completed At</span><strong>{{ $order->completed_at?->format('M d, Y h:i A') ?? 'Not completed' }}</strong></div>
            @if($order->notes)
                <div class="order-detail-field"><span>Order Notes</span><strong>{{ $order->notes }}</strong></div>
            @endif
        </article>

        <article class="order-detail-card">
            <h2>Customer Information</h2>
            @if($order->customer)
                <div class="order-detail-field"><span>Name</span><strong>{{ $order->customer->first_name }} {{ $order->customer->last_name }}</strong></div>
                <div class="order-detail-field"><span>Phone</span><strong>{{ $order->customer->phone ?: 'N/A' }}</strong></div>
                <div class="order-detail-field"><span>Email</span><strong>{{ $order->customer->email ?: 'N/A' }}</strong></div>
                <div class="order-detail-field"><span>Address</span><strong>{{ $order->customer->address ?: 'N/A' }}</strong></div>
            @else
                <div class="order-detail-empty">No customer record linked to this order.</div>
            @endif
        </article>
    </section>

    <article class="order-detail-card">
        <h2>Ordered Items</h2>
        @if($order->orderItems->isNotEmpty())
            <div class="order-detail-table-wrap">
                <table class="order-detail-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th class="numeric">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->orderItems as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->variant_name)
                                        <div class="order-detail-muted">{{ $item->variant_name }}</div>
                                    @endif
                                    @foreach($item->addOns as $addOn)
                                        <div class="order-detail-muted">
                                            + {{ $addOn->add_on_name }}
                                            ({{ $addOn->quantity }} × ₱{{ number_format((float) $addOn->unit_price, 2) }})
                                        </div>
                                    @endforeach
                                    @if($item->notes)
                                        <div class="order-detail-muted">Note: {{ $item->notes }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="numeric"><strong>₱{{ number_format((float) $item->subtotal, 2) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="order-detail-empty">No order items recorded.</div>
        @endif
    </article>

    <section class="order-detail-grid">
        <article class="order-detail-card">
            <h2>Financial Summary</h2>
            <div class="order-detail-field"><span>Subtotal</span><strong>₱{{ number_format((float) $order->subtotal, 2) }}</strong></div>
            <div class="order-detail-field"><span>Discount</span><strong>₱{{ number_format((float) $order->discount_amount, 2) }}</strong></div>
            <div class="order-detail-field"><span>Tax</span><strong>₱{{ number_format((float) $order->tax_amount, 2) }}</strong></div>
            <div class="order-detail-field"><span>Delivery Fee</span><strong>₱{{ number_format((float) $order->delivery_fee, 2) }}</strong></div>
            <div class="order-detail-field order-detail-total"><span>Total Amount</span><strong>₱{{ number_format((float) $order->total_amount, 2) }}</strong></div>
        </article>

        <article class="order-detail-card">
            <h2>Payments</h2>
            <div class="order-detail-field"><span>Total Order Amount</span><strong>₱{{ number_format($totalAmountCents / 100, 2) }}</strong></div>
            <div class="order-detail-field"><span>Total Paid</span><strong>₱{{ number_format($paidAmountCents / 100, 2) }}</strong></div>
            <div class="order-detail-field"><span>Remaining Balance</span><strong>₱{{ number_format($remainingBalanceCents / 100, 2) }}</strong></div>
            <div class="order-detail-field">
                <span>Payment Status</span>
                <strong><span class="fm-badge {{ $paymentBadge }}">{{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}</span></strong>
            </div>

            @if($remainingBalanceCents > 0 && $order->status !== 'cancelled')
                <div class="order-detail-payment-box">
                    <h3>Record Payment</h3>
                    <form method="POST" action="{{ route('cashier.orders.payments.store', $order) }}">
                        @csrf
                        <div class="order-detail-form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select payment method</option>
                                @foreach(['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer', 'card' => 'Card', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <div class="order-detail-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="order-detail-form-group">
                            <label for="payment_amount">Amount (₱)</label>
                            <input id="payment_amount" type="number" name="amount"
                                min="0.01"
                                max="{{ number_format($remainingBalanceCents / 100, 2, '.', '') }}"
                                step="0.01" value="{{ old('amount') }}"
                                placeholder="Enter payment amount" required>
                            @error('amount')
                                <div class="order-detail-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="order-detail-form-group">
                            <label for="transaction_reference">Transaction Reference (optional)</label>
                            <input id="transaction_reference" type="text" name="transaction_reference"
                                maxlength="120" value="{{ old('transaction_reference') }}"
                                placeholder="Enter reference number">
                            @error('transaction_reference')
                                <div class="order-detail-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="order-detail-form-group">
                            <label for="payment_notes">Notes (optional)</label>
                            <textarea id="payment_notes" name="notes" maxlength="2000" rows="3"
                                placeholder="Additional payment details">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="order-detail-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="fm-btn fm-btn-block">Record Payment</button>
                    </form>
                </div>
            @endif

            <h3>Payment History</h3>
            @forelse($order->payments as $payment)
                <div class="order-detail-field">
                    <span>
                        {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                        <div class="order-detail-muted">{{ $payment->payment_number }}</div>
                    </span>
                    <strong>
                        ₱{{ number_format((float) $payment->amount, 2) }}
                        <div class="order-detail-muted">{{ ucfirst($payment->status) }}</div>
                    </strong>
                </div>
                <div class="order-detail-muted" style="margin:8px 0 14px;">
                    Received by: {{ $payment->receivedBy?->name ?? 'N/A' }}<br>
                    Paid at: {{ $payment->paid_at?->format('M d, Y h:i A') ?? 'N/A' }}
                    @if($payment->transaction_reference)
                        <br>Reference: {{ $payment->transaction_reference }}
                    @endif
                </div>
            @empty
                <div class="order-detail-empty">No payment records available.</div>
            @endforelse
        </article>
    </section>

    @if($order->deliveryDetail)
        <article class="order-detail-card">
            <h2>Delivery Information</h2>
            <div class="order-detail-field"><span>Recipient</span><strong>{{ $order->deliveryDetail->recipient_name }}</strong></div>
            <div class="order-detail-field"><span>Phone</span><strong>{{ $order->deliveryDetail->recipient_phone }}</strong></div>
            <div class="order-detail-field"><span>Address</span><strong>{{ $order->deliveryDetail->delivery_address }}</strong></div>
            <div class="order-detail-field"><span>Instructions</span><strong>{{ $order->deliveryDetail->delivery_instructions ?: 'None' }}</strong></div>
            <div class="order-detail-field"><span>Delivery Staff</span><strong>{{ $order->deliveryDetail->deliveryStaff?->name ?? 'Unassigned' }}</strong></div>
            <div class="order-detail-field"><span>Delivery Status</span><strong>{{ ucfirst(str_replace('_', ' ', $order->deliveryDetail->status)) }}</strong></div>
            <div class="order-detail-field"><span>Delivered At</span><strong>{{ $order->deliveryDetail->delivered_at?->format('M d, Y h:i A') ?? 'Not delivered' }}</strong></div>

            @if($order->deliveryDetail->failure_reason)
                <div class="order-detail-field"><span>Failure Reason</span><strong>{{ $order->deliveryDetail->failure_reason }}</strong></div>
            @endif

            <h3>Delivery Status History</h3>
            @forelse($order->deliveryDetail->statusHistory->sortBy('changed_at') as $history)
                <div class="order-detail-timeline" style="margin-bottom:18px;">
                    <div class="order-detail-timeline-item">
                        <div class="order-detail-transition">
                            {{ ucfirst(str_replace('_', ' ', $history->previous_status ?? 'New')) }}
                            → {{ ucfirst(str_replace('_', ' ', $history->new_status)) }}
                        </div>
                        <div class="order-detail-muted" style="margin-top:6px;">
                            {{ $history->changed_at?->format('M d, Y h:i A') ?? 'N/A' }}
                            · {{ $history->updatedBy?->name ?? 'System' }}
                        </div>
                        @if($history->remarks)
                            <div class="order-detail-remarks">{{ $history->remarks }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="order-detail-empty">No delivery history available.</div>
            @endforelse

            @php
                $deliveryActionMessage = [
                    'pending' => 'This delivery is waiting for an administrator to assign a delivery staff member.',
                    'assigned' => 'A delivery staff member has been assigned and can proceed with pickup once the order is ready.',
                    'picked_up' => 'The order has been picked up and is now out for delivery.',
                    'delivered' => 'The order has been successfully delivered.',
                    'failed' => 'The delivery attempt was unsuccessful. Review the failure reason above.',
                ][$order->deliveryDetail->status] ?? 'Check the delivery details for the latest progress.';
            @endphp

            <h3>Delivery Actions</h3>
            <p class="order-detail-muted">{{ $deliveryActionMessage }}</p>

            @if(auth()->user()->role === 'admin')
                @if($order->deliveryDetail->status === 'pending' && !$order->deliveryDetail->delivery_staff_id)
                    <a href="{{ route('admin.deliveries.index') }}" class="fm-btn">Go to Delivery Assignments</a>
                @else
                    <a href="{{ route('delivery.show', $order->deliveryDetail) }}" class="fm-btn">View Delivery Details</a>
                @endif
            @endif
        </article>
    @endif

    @if(in_array(auth()->user()->role, ['admin', 'cashier'], true))
        @php
            $nextOrderStatus = [
                'pending' => 'confirmed',
                'confirmed' => 'preparing',
                'preparing' => 'ready',
            ][$order->status] ?? null;

            $nextOrderLabel = [
                'confirmed' => 'Confirm Order',
                'preparing' => 'Start Preparing',
                'ready' => 'Mark as Ready',
            ][$nextOrderStatus] ?? null;

            $canCompleteOrder = $order->status === 'ready'
                && in_array($order->order_type, ['dine_in', 'takeout'], true);
        @endphp

        @if($nextOrderStatus || $canCompleteOrder)
            <article class="order-detail-card" id="orderActionsCard">
                <h2>Order Actions</h2>
                <p class="order-detail-muted">
                    Update this order one step at a time. Each update will be recorded in the order timeline.
                </p>

                @if($nextOrderStatus)
                    <form method="POST" action="{{ route('cashier.orders.status.update', $order) }}" id="orderStatusForm">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextOrderStatus }}">

                        <div class="order-detail-form-group">
                            <label for="statusRemarks">Remarks (optional)</label>
                            <textarea id="statusRemarks" name="remarks" maxlength="1000" rows="3"
                                placeholder="Add a note about this status update...">{{ old('remarks') }}</textarea>
                        </div>

                        <div class="order-detail-actions">
                            <button type="button" class="fm-btn" id="statusActionButton">{{ $nextOrderLabel }}</button>
                        </div>
                    </form>
                @endif

                @if($canCompleteOrder)
                    <form method="POST" action="{{ route('cashier.orders.complete', $order) }}"
                        id="completeOrderForm" style="margin-top:15px;">
                        @csrf
                        <div class="order-detail-actions">
                            <button type="button" class="fm-btn fm-btn-secondary" id="completeOrderButton">Complete Order</button>
                        </div>
                        <p class="order-detail-muted" style="margin-top:10px;">
                            Completing this order will deduct its ingredients from inventory.
                        </p>
                    </form>
                @endif
            </article>
        @endif
    @endif

    <article class="order-detail-card">
        <h2>Order Status Timeline</h2>
        @forelse($order->statusHistory as $history)
            <div class="order-detail-timeline" style="margin-bottom:18px;">
                <div class="order-detail-timeline-item">
                    <div class="order-detail-transition">
                        {{ ucfirst(str_replace('_', ' ', $history->previous_status ?? 'New')) }}
                        → {{ ucfirst(str_replace('_', ' ', $history->new_status)) }}
                    </div>
                    <div class="order-detail-muted" style="margin-top:6px;">
                        {{ $history->changed_at?->format('M d, Y h:i A') ?? 'N/A' }}
                        · Updated by {{ $history->updatedBy?->name ?? 'System' }}
                    </div>
                    @if($history->remarks)
                        <div class="order-detail-remarks">{{ $history->remarks }}</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="order-detail-empty">No order status history recorded yet.</div>
        @endforelse
    </article>
</div>

<div class="order-detail-modal-backdrop" id="actionConfirmModal" aria-hidden="true">
    <div class="order-detail-modal" role="dialog" aria-modal="true"
        aria-labelledby="actionConfirmTitle" aria-describedby="actionConfirmMessage">
        <h2 id="actionConfirmTitle">Confirm Action</h2>
        <p id="actionConfirmMessage"></p>
        <div class="order-detail-modal-buttons">
            <button type="button" class="fm-btn fm-btn-secondary" id="cancelActionButton">Cancel</button>
            <button type="button" class="fm-btn" id="confirmActionButton">Continue</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('actionConfirmModal');
    if (!modal) return;

    const title = document.getElementById('actionConfirmTitle');
    const message = document.getElementById('actionConfirmMessage');
    const confirmButton = document.getElementById('confirmActionButton');
    const cancelButton = document.getElementById('cancelActionButton');

    let pendingForm = null;

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        pendingForm = null;
    }

    function openModal(form, heading, description, buttonText) {
        pendingForm = form;
        title.textContent = heading;
        message.textContent = description;
        confirmButton.textContent = buttonText;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        cancelButton.focus();
    }

    const statusButton = document.getElementById('statusActionButton');
    if (statusButton) {
        statusButton.addEventListener('click', () => {
            const form = document.getElementById('orderStatusForm');
            const nextStatus = form.querySelector('input[name="status"]').value;
            const labels = {
                confirmed: 'Confirm Order',
                preparing: 'Start Preparing',
                ready: 'Mark as Ready'
            };

            openModal(
                form,
                labels[nextStatus],
                'Are you sure you want to update this order status?',
                'Update Status'
            );
        });
    }

    const completeButton = document.getElementById('completeOrderButton');
    if (completeButton) {
        completeButton.addEventListener('click', () => {
            openModal(
                document.getElementById('completeOrderForm'),
                'Complete Order',
                'This action will deduct the ingredients used by this order from inventory. Do you want to continue?',
                'Complete Order'
            );
        });
    }

    confirmButton.addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.submit();
    });

    cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('open')) {
            closeModal();
        }
    });
})();
</script>
@endpush