@extends('layouts.app')

@section('title', 'Delivery Records')

@section('breadcrumb', 'Delivery Management / Delivery Records')

@section('content')
<div class="fm-page">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">Delivery Records</h1>
            <p class="fm-page-description">
                @if(auth()->user()->role === 'admin')
                    Monitor and review all delivery transactions and their current status.
                @else
                    View and monitor the deliveries assigned to your account.
                @endif
            </p>
        </div>

        @if(auth()->user()->role === 'admin')
            <a href="{{ route('admin.index') }}" class="fm-btn fm-btn-secondary">
                Back to Admin Dashboard
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="fm-btn fm-btn-secondary">
                Back to Dashboard
            </a>
        @endif
    </div>

    <div class="fm-card">
        <div class="fm-delivery-records-heading">
            <div>
                <h2 class="fm-card-title">All Delivery Records</h2>
                <p class="fm-muted">
                    Review order recipients, assigned delivery staff, and delivery progress.
                </p>
            </div>

            <span class="fm-badge fm-badge-neutral">
                {{ $deliveries->total() }} records
            </span>
        </div>

        @if($deliveries->isEmpty())
            <div class="fm-empty fm-delivery-records-empty">
                <div class="fm-delivery-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M3 7h18v13H3z"/>
                        <path d="M3 7l2-4h14l2 4"/>
                        <path d="M8 12h8"/>
                        <path d="M8 16h5"/>
                    </svg>
                </div>
                <h3>No delivery records available</h3>
                <p class="fm-muted">
                    Delivery records will appear here when eligible orders have been created.
                </p>
            </div>
        @else
            <div class="fm-table-wrap fm-delivery-records-table">
                <table class="fm-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Recipient</th>
                            <th>Delivery Staff</th>
                            <th>Status</th>
                            <th class="fm-delivery-action-heading">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                            @php
                                $statusClass = match ($delivery->status) {
                                    'delivered' => 'fm-badge-success',
                                    'failed', 'cancelled' => 'fm-badge-danger',
                                    'pending' => 'fm-badge-warning',
                                    'assigned', 'picked_up' => 'fm-badge-info',
                                    default => 'fm-badge-neutral',
                                };
                            @endphp

                            <tr>
                                <td>
                                    <span class="fm-delivery-order-number">
                                        {{ $delivery->order->order_number }}
                                    </span>
                                </td>

                                <td>
                                    <span class="fm-delivery-recipient">
                                        {{ $delivery->recipient_name }}
                                    </span>
                                </td>

                                <td>
                                    @if($delivery->deliveryStaff)
                                        <span class="fm-delivery-staff">
                                            {{ $delivery->deliveryStaff->name }}
                                        </span>
                                    @else
                                        <span class="fm-muted">Unassigned</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="fm-badge {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                                    </span>
                                </td>

                                <td class="fm-delivery-action-cell">
                                    <a href="{{ route('delivery.show', $delivery) }}"
                                       class="fm-btn fm-btn-outline fm-btn-sm">
                                        View details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="fm-delivery-pagination">
                <div class="fm-muted fm-delivery-pagination-count">
                    Showing {{ $deliveries->firstItem() }} to
                    {{ $deliveries->lastItem() }} of
                    {{ $deliveries->total() }} records
                </div>

                <div>
                    {{ $deliveries->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

@push('head')
<style>
    .fm-delivery-records-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
    }

    .fm-delivery-records-heading .fm-card-title {
        margin-bottom: 6px;
    }

    .fm-delivery-records-heading p {
        margin: 0;
        font-size: 13px;
    }

    .fm-delivery-records-table {
        width: 100%;
    }

    .fm-delivery-order-number {
        color: var(--fm-text);
        font-weight: 650;
        white-space: nowrap;
    }

    .fm-delivery-recipient,
    .fm-delivery-staff {
        color: var(--fm-text);
        font-weight: 500;
    }

    .fm-delivery-action-heading,
    .fm-delivery-action-cell {
        text-align: right !important;
    }

    .fm-btn-sm {
        padding: 7px 12px;
        font-size: 12px;
        white-space: nowrap;
    }

    .fm-delivery-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 22px;
    }

    .fm-delivery-pagination-count {
        font-size: 13px;
    }

    .fm-delivery-records-empty {
        padding: 48px 20px;
        text-align: center;
    }

    .fm-delivery-records-empty h3 {
        margin: 14px 0 7px;
        color: var(--fm-text);
        font-size: 16px;
    }

    .fm-delivery-records-empty p {
        max-width: 420px;
        margin: 0 auto;
        font-size: 13px;
    }

    .fm-delivery-empty-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        border-radius: 14px;
        color: var(--fm-primary);
        background: var(--fm-surface-soft);
    }

    .fm-delivery-empty-icon svg {
        width: 24px;
        height: 24px;
    }

    @media (max-width: 700px) {
        .fm-delivery-records-heading {
            align-items: flex-start;
        }

        .fm-delivery-pagination {
            align-items: flex-start;
            flex-direction: column;
        }

        .fm-delivery-action-heading,
        .fm-delivery-action-cell {
            text-align: left !important;
        }
    }
</style>
@endpush
@endsection