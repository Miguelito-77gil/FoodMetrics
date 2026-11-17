@extends('layouts.app')

@section('title', 'Delivery Details')

@section('breadcrumb', 'Delivery Management / Delivery Details')

@section('content')
@php
    $statusClass = match ($delivery->status) {
        'delivered' => 'fm-badge-success',
        'failed', 'cancelled' => 'fm-badge-danger',
        'pending' => 'fm-badge-warning',
        'assigned', 'picked_up' => 'fm-badge-info',
        default => 'fm-badge-neutral',
    };

    $nextStatuses = match ($delivery->status) {
        'assigned' => ['picked_up'],
        'picked_up' => ['delivered', 'failed'],
        default => [],
    };

    $orderStatusClass = match ($delivery->order->status) {
        'completed' => 'fm-badge-success',
        'cancelled' => 'fm-badge-danger',
        'ready', 'out_for_delivery' => 'fm-badge-info',
        default => 'fm-badge-neutral',
    };
@endphp

<div class="fm-page">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">Delivery Details</h1>
            <p class="fm-page-description">
                View delivery information, update its progress, and review status history.
            </p>
        </div>

        <a href="{{ route('delivery.index') }}" class="fm-btn fm-btn-secondary">
            Back to Deliveries
        </a>
    </div>


    <div class="fm-delivery-detail-grid">
        <div class="fm-delivery-main-column">
            <section class="fm-card">
                <div class="fm-delivery-section-heading">
                    <div>
                        <h2 class="fm-card-title">Delivery Information</h2>
                        <p class="fm-muted">Order #{{ $delivery->order->order_number }}</p>
                    </div>

                    <span class="fm-badge {{ $statusClass }}">
                        {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                    </span>
                </div>

                <div class="fm-delivery-info-grid">
                    <div class="fm-delivery-info-item">
                        <span class="fm-label">Recipient</span>
                        <strong>{{ $delivery->recipient_name }}</strong>
                    </div>

                    <div class="fm-delivery-info-item">
                        <span class="fm-label">Recipient Phone</span>
                        <strong>{{ $delivery->recipient_phone }}</strong>
                    </div>

                    <div class="fm-delivery-info-item fm-delivery-info-full">
                        <span class="fm-label">Delivery Address</span>
                        <strong>{{ $delivery->delivery_address }}</strong>
                    </div>

                    <div class="fm-delivery-info-item fm-delivery-info-full">
                        <span class="fm-label">Delivery Instructions</span>
                        <strong>{{ $delivery->delivery_instructions ?: 'None provided' }}</strong>
                    </div>

                    <div class="fm-delivery-info-item">
                        <span class="fm-label">Delivery Staff</span>
                        <strong>{{ $delivery->deliveryStaff?->name ?? 'Unassigned' }}</strong>
                    </div>

                    <div class="fm-delivery-info-item">
                        <span class="fm-label">Delivery Fee</span>
                        <strong>₱{{ number_format((float) $delivery->delivery_fee, 2) }}</strong>
                    </div>
                </div>
            </section>

            <section class="fm-card fm-delivery-order-card">
                <h2 class="fm-card-title">Related Order</h2>

                <div class="fm-delivery-order-summary">
                    <div>
                        <span class="fm-label">Order Number</span>
                        <strong>{{ $delivery->order->order_number }}</strong>
                    </div>

                    <div>
                        <span class="fm-label">Order Status</span>
                        <span class="fm-badge {{ $orderStatusClass }}">
                            {{ ucfirst(str_replace('_', ' ', $delivery->order->status)) }}
                        </span>
                    </div>

                    <div>
                        <span class="fm-label">Order Total</span>
                        <strong class="fm-delivery-order-total">
                            ₱{{ number_format((float) $delivery->order->total_amount, 2) }}
                        </strong>
                    </div>
                </div>
            </section>

            <section class="fm-card fm-delivery-history-card">
                <div class="fm-delivery-section-heading">
                    <div>
                        <h2 class="fm-card-title">Delivery Status History</h2>
                        <p class="fm-muted">A record of changes made to this delivery.</p>
                    </div>
                </div>

                @forelse($delivery->statusHistory->sortByDesc('changed_at') as $history)
                    <div class="fm-delivery-history-item">
                        <div class="fm-delivery-history-marker"></div>

                        <div class="fm-delivery-history-content">
                            <strong class="fm-delivery-history-transition">
                                {{ ucfirst(str_replace('_', ' ', $history->previous_status ?? 'New')) }}
                                <span aria-hidden="true">→</span>
                                {{ ucfirst(str_replace('_', ' ', $history->new_status)) }}
                            </strong>

                            <div class="fm-muted fm-delivery-history-date">
                                {{ $history->changed_at?->format('M d, Y h:i A') ?? 'Date unavailable' }}
                            </div>

                            @if($history->remarks)
                                <p class="fm-delivery-history-remarks">
                                    {{ $history->remarks }}
                                </p>
                            @endif

                            <small class="fm-muted">
                                Updated by: {{ $history->updatedBy?->name ?? 'System' }}
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="fm-empty fm-delivery-history-empty">
                        No status history available yet.
                    </div>
                @endforelse
            </section>
        </div>

        <aside class="fm-delivery-side-column">
            <section class="fm-card">
                <h2 class="fm-card-title">Delivery Status</h2>

                <div class="fm-delivery-current-status">
                    <span class="fm-label">Current Status</span>
                    <span class="fm-badge {{ $statusClass }}">
                        {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                    </span>
                </div>

                @if(count($nextStatuses) > 0)
                    <div class="fm-divider"></div>

                    <h3 class="fm-delivery-form-title">Update Delivery Status</h3>
                    <p class="fm-muted fm-delivery-form-description">
                        Select the next available status for this delivery.
                    </p>

                    <form method="POST"
                          action="{{ route('delivery.status.update', $delivery) }}"
                          id="delivery-status-form">
                        @csrf
                        @method('PATCH')

                        <div class="fm-field">
                            <label for="status" class="fm-label">New Status</label>
                            <select id="status" name="status" class="fm-select" required>
                                <option value="">Select status</option>
                                @foreach($nextStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="fm-delivery-field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="fm-field" id="failure-reason-field">
                            <label for="failure_reason" class="fm-label">
                                Failure Reason
                                <span class="fm-muted">(required if failed)</span>
                            </label>
                            <textarea
                                id="failure_reason"
                                name="failure_reason"
                                class="fm-textarea"
                                maxlength="1000"
                                placeholder="Explain why the delivery could not be completed..."
                            >{{ old('failure_reason') }}</textarea>
                            @error('failure_reason')
                                <p class="fm-delivery-field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="fm-field">
                            <label for="remarks" class="fm-label">
                                Remarks <span class="fm-muted">(optional)</span>
                            </label>
                            <textarea
                                id="remarks"
                                name="remarks"
                                class="fm-textarea"
                                maxlength="1000"
                                placeholder="Add additional delivery remarks..."
                            >{{ old('remarks') }}</textarea>
                            @error('remarks')
                                <p class="fm-delivery-field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="fm-btn fm-btn-primary fm-delivery-submit">
                            Save Status Update
                        </button>
                    </form>
                @else
                    <div class="fm-delivery-terminal-notice">
                        <strong>No further updates available</strong>
                        <p>
                            This delivery is in a terminal status. No further staff status updates are available.
                        </p>
                    </div>
                @endif
            </section>
        </aside>
    </div>
</div>

@push('head')
<style>
    .fm-delivery-alert {
        margin-bottom: 20px;
    }

    .fm-delivery-alert ul {
        margin: 8px 0 0;
        padding-left: 20px;
    }

    .fm-delivery-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        align-items: start;
        gap: 20px;
    }

    .fm-delivery-main-column,
    .fm-delivery-side-column {
        display: grid;
        gap: 20px;
        min-width: 0;
    }

    .fm-delivery-section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 22px;
    }

    .fm-delivery-section-heading .fm-card-title {
        margin-bottom: 5px;
    }

    .fm-delivery-section-heading p {
        margin: 0;
        font-size: 13px;
    }

    .fm-delivery-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 24px;
    }

    .fm-delivery-info-item {
        display: flex;
        flex-direction: column;
        gap: 7px;
        min-width: 0;
        padding: 15px 0;
        border-bottom: 1px solid var(--fm-border);
    }

    .fm-delivery-info-item strong {
        color: var(--fm-text);
        font-size: 14px;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .fm-delivery-info-full {
        grid-column: 1 / -1;
    }

    .fm-delivery-order-card {
        display: grid;
        gap: 18px;
    }

    .fm-delivery-order-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .fm-delivery-order-summary > div {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
    }

    .fm-delivery-order-summary strong {
        color: var(--fm-text);
        font-size: 14px;
        overflow-wrap: anywhere;
    }

    .fm-delivery-order-total {
        font-size: 17px !important;
    }

    .fm-delivery-current-status {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 9px;
        margin-top: 18px;
    }

    .fm-delivery-form-title {
        margin: 0 0 7px;
        color: var(--fm-text);
        font-size: 15px;
        font-weight: 650;
    }

    .fm-delivery-form-description {
        margin: 0 0 18px;
        font-size: 12px;
        line-height: 1.6;
    }

    .fm-delivery-side-column .fm-field {
        margin-bottom: 16px;
    }

    .fm-delivery-side-column .fm-label {
        display: block;
        margin-bottom: 7px;
    }

    .fm-delivery-side-column .fm-textarea {
        min-height: 88px;
        resize: vertical;
    }

    .fm-delivery-submit {
        width: 100%;
        margin-top: 4px;
    }

    .fm-delivery-field-error {
        margin: 6px 0 0;
        color: var(--fm-danger);
        font-size: 12px;
    }

    .fm-delivery-terminal-notice {
        margin-top: 20px;
        padding: 14px;
        border: 1px solid var(--fm-border);
        border-radius: 10px;
        background: var(--fm-surface-soft);
    }

    .fm-delivery-terminal-notice strong {
        color: var(--fm-text);
        font-size: 13px;
    }

    .fm-delivery-terminal-notice p {
        margin: 7px 0 0;
        color: var(--fm-muted);
        font-size: 12px;
        line-height: 1.6;
    }

    .fm-delivery-history-card {
        min-width: 0;
    }

    .fm-delivery-history-item {
        position: relative;
        display: flex;
        gap: 13px;
        padding: 4px 0 23px;
    }

    .fm-delivery-history-item:not(:last-child)::after {
        position: absolute;
        top: 17px;
        bottom: 0;
        left: 5px;
        width: 2px;
        background: var(--fm-border);
        content: "";
    }

    .fm-delivery-history-marker {
        position: relative;
        z-index: 1;
        flex: 0 0 12px;
        width: 12px;
        height: 12px;
        margin-top: 4px;
        border: 3px solid var(--fm-primary);
        border-radius: 50%;
        background: var(--fm-surface);
    }

    .fm-delivery-history-content {
        min-width: 0;
    }

    .fm-delivery-history-transition {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        color: var(--fm-text);
        font-size: 13px;
    }

    .fm-delivery-history-transition span {
        color: var(--fm-primary);
    }

    .fm-delivery-history-date {
        margin-top: 6px;
        font-size: 12px;
    }

    .fm-delivery-history-remarks {
        margin: 9px 0;
        color: var(--fm-text);
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .fm-delivery-history-content small {
        display: block;
        margin-top: 8px;
        font-size: 11px;
    }

    .fm-delivery-history-empty {
        padding: 22px 12px;
        font-size: 13px;
    }

    @media (max-width: 1050px) {
        .fm-delivery-detail-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .fm-delivery-side-column {
            grid-row: 1;
        }
    }

    @media (max-width: 600px) {
        .fm-delivery-info-grid,
        .fm-delivery-order-summary {
            grid-template-columns: minmax(0, 1fr);
        }

        .fm-delivery-info-full {
            grid-column: auto;
        }

        .fm-delivery-section-heading {
            flex-direction: column;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const statusSelect = document.getElementById('status');
        const failureField = document.getElementById('failure-reason-field');
        const failureInput = document.getElementById('failure_reason');

        if (!statusSelect || !failureField || !failureInput) {
            return;
        }

        const updateFailureField = () => {
            const isFailed = statusSelect.value === 'failed';

            failureField.hidden = !isFailed;
            failureInput.required = isFailed;
        };

        statusSelect.addEventListener('change', updateFailureField);
        updateFailureField();
    })();
</script>
@endpush
@endsection