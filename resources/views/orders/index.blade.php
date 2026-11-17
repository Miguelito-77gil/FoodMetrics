@extends('layouts.app')

@section('title', 'Order Management')
@section('breadcrumb', 'Order Management')

@section('content')
<div class="fm-page order-management-page">

    <div class="fm-page-header order-page-header">
        <div>
            <h1 class="fm-page-title">Order Management</h1>
            <p class="fm-page-description">
                Review restaurant orders and open their complete details and status history.
            </p>
        </div>
        <div class="order-header-actions">
            <a href="{{ route('cashier.orders.create') }}" class="order-new-button">
                <span aria-hidden="true">+</span>
                New Order
            </a>
        </div>
    </div>

    <div class="order-overview">
        <div class="order-overview-content">
            <div class="order-overview-label">Total Orders</div>
            <div class="order-overview-value">{{ number_format($totalOrders) }}</div>
            <div class="order-overview-description">
                All recorded restaurant transactions
            </div>
        </div>

        <div class="order-overview-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3h8l4 4v14H4V3h4Z"/>
                <path d="M8 3v5h8V3M8 13h8M8 17h5"/>
            </svg>
        </div>
    </div>

    <section class="fm-card order-list-card">
        <div class="order-list-heading">
            <div>
                <h2 class="fm-card-title">Restaurant Orders</h2>
                <p class="order-list-description">
                    Latest orders are displayed first.
                </p>
            </div>

            <span class="order-count">
                {{ $orders->total() }} {{ $orders->total() === 1 ? 'order' : 'orders' }}
            </span>
        </div>

        <form method="GET" action="{{ route('orders.index') }}" class="order-filter-form">
            <div class="order-filter-field order-search-field">
                <label for="orderSearch">Search orders</label>
                <input type="search" id="orderSearch" name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Order number or customer" maxlength="100">
            </div>

            <div class="order-filter-field">
                <label for="orderStatusFilter">Status</label>
                <select id="orderStatusFilter" name="status">
                    <option value="">All statuses</option>
                    @foreach([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'out_for_delivery' => 'Out for delivery',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="order-filter-field">
                <label for="orderTypeFilter">Order type</label>
                <select id="orderTypeFilter" name="type">
                    <option value="">All types</option>
                    <option value="dine_in" @selected(($filters['type'] ?? '') === 'dine_in')>Dine-in</option>
                    <option value="takeout" @selected(($filters['type'] ?? '') === 'takeout')>Takeout</option>
                    <option value="delivery" @selected(($filters['type'] ?? '') === 'delivery')>Delivery</option>
                </select>
            </div>

            <div class="order-filter-field">
                <label for="orderDateFrom">From date</label>
                <input type="date" id="orderDateFrom" name="date_from"
                       value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="order-filter-field">
                <label for="orderDateTo">To date</label>
                <input type="date" id="orderDateTo" name="date_to"
                       value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="order-filter-actions">
                <button type="submit" class="order-filter-button">Apply Filters</button>
                <a href="{{ route('orders.index') }}" class="order-clear-button">Clear</a>
            </div>
        </form>

        @if($orders->isNotEmpty())
            <div class="fm-table-wrap order-table-wrap">
                <table class="fm-table order-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Order Type</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Ordered At</th>
                            <th class="order-action-heading">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <span class="order-number">
                                        {{ $order->order_number }}
                                    </span>
                                </td>

                                <td>
                                    @if($order->customer)
                                        <span class="order-customer">
                                            {{ $order->customer->first_name }}
                                            {{ $order->customer->last_name }}
                                        </span>
                                    @else
                                        <span class="fm-muted">Walk-in / Unlinked</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="order-type">
                                        {{ ucfirst(str_replace('_', ' ', $order->order_type)) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="order-status-badge order-status-{{ str_replace('_', '-', $order->status) }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="order-total">
                                        ₱{{ number_format((float) $order->total_amount, 2) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="order-date">
                                        {{ $order->ordered_at?->format('M d, Y') ?? 'N/A' }}
                                    </span>
                                    @if($order->ordered_at)
                                        <span class="order-time">
                                            {{ $order->ordered_at->format('h:i A') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="order-action-cell">
                                    <a
                                        class="order-view-link"
                                        href="{{ route('orders.show', $order) }}"
                                    >
                                        View Details
                                        <svg viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round"
                                             aria-hidden="true">
                                            <path d="M5 12h14M13 6l6 6-6 6"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="order-pagination">
                <div class="order-pagination-info">
                    Showing
                    <strong>{{ $orders->firstItem() ?? 0 }}</strong>
                    to
                    <strong>{{ $orders->lastItem() ?? 0 }}</strong>
                    of
                    <strong>{{ $orders->total() }}</strong>
                    orders
                </div>

                <div class="order-pagination-controls">
                    @if($orders->previousPageUrl())
                        <a class="order-page-link" href="{{ $orders->previousPageUrl() }}">
                            Previous
                        </a>
                    @else
                        <span class="order-page-link is-disabled">Previous</span>
                    @endif

                    <span class="order-page-number">
                        Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}
                    </span>

                    @if($orders->nextPageUrl())
                        <a class="order-page-link" href="{{ $orders->nextPageUrl() }}">
                            Next
                        </a>
                    @else
                        <span class="order-page-link is-disabled">Next</span>
                    @endif
                </div>
            </div>
        @else
            <div class="order-empty-state">
                <div class="order-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 3h8l4 4v14H4V3h4Z"/>
                        <path d="M8 3v5h8V3M8 13h8M8 17h5"/>
                    </svg>
                </div>
                <h3>No orders found</h3>
                <p>Orders will appear here when transactions are recorded.</p>
            </div>
        @endif
    </section>
</div>
@endsection

@push('head')
<style>
    .order-management-page {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .order-page-header {
        margin-bottom: 0;
    }

    .order-overview {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px;
        background: var(--fm-surface);
        border: 1px solid var(--fm-border);
        border-radius: 14px;
    }

    .order-overview-label {
        color: var(--fm-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .order-overview-value {
        margin-top: 7px;
        color: var(--fm-text);
        font-size: 30px;
        font-weight: 750;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }

    .order-overview-description {
        margin-top: 5px;
        color: var(--fm-muted);
        font-size: 12px;
    }

    .order-overview-icon,
    .order-empty-icon {
        display: grid;
        flex-shrink: 0;
        place-items: center;
        width: 48px;
        height: 48px;
        color: var(--fm-primary);
        background: var(--fm-surface-soft);
        border-radius: 12px;
    }

    .order-overview-icon svg,
    .order-empty-icon svg {
        width: 23px;
        height: 23px;
    }

    .order-list-card {
        min-width: 0;
        padding: 0;
        overflow: hidden;
    }

    .order-list-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 21px 22px;
        border-bottom: 1px solid var(--fm-border);
    }

    .order-list-heading .fm-card-title {
        margin: 0;
    }

    .order-list-description {
        margin: 5px 0 0;
        color: var(--fm-muted);
        font-size: 12px;
    }

    .order-count {
        padding: 6px 10px;
        color: var(--fm-primary-hover);
        background: var(--fm-surface-soft);
        border: 1px solid var(--fm-border);
        border-radius: 20px;
        font-size: 11px;
        font-weight: 650;
        white-space: nowrap;
    }

    .order-table-wrap {
        border: 0;
        border-radius: 0;
    }

    .order-table {
        min-width: 850px;
    }

    .order-table th {
        white-space: nowrap;
    }

    .order-table td {
        vertical-align: middle;
    }

    .order-number {
        color: var(--fm-text);
        font-weight: 700;
        white-space: nowrap;
    }

    .order-customer {
        color: var(--fm-text);
        font-weight: 550;
    }

    .order-type {
        color: var(--fm-muted);
        text-transform: capitalize;
    }

    .order-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border: 1px solid transparent;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 650;
        line-height: 1.3;
        white-space: nowrap;
    }

    .order-status-pending {
        color: #876B32;
        background: #F8F1DE;
        border-color: #EEE2C3;
    }

    .order-status-confirmed,
    .order-status-preparing,
    .order-status-ready,
    .order-status-out-for-delivery {
        color: #526C82;
        background: #EAF0F4;
        border-color: #D9E4EB;
    }

    .order-status-completed,
    .order-status-delivered {
        color: #527456;
        background: #EAF1E8;
        border-color: #D9E6D6;
    }

    .order-status-cancelled,
    .order-status-failed {
        color: #985D55;
        background: #F7EDEA;
        border-color: #ECDAD5;
    }

    .order-total {
        color: var(--fm-text);
        font-weight: 650;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .order-date,
    .order-time {
        display: block;
        white-space: nowrap;
    }

    .order-date {
        color: var(--fm-text);
        font-weight: 550;
    }

    .order-time {
        margin-top: 3px;
        color: var(--fm-muted);
        font-size: 11px;
    }

    .order-action-heading,
    .order-action-cell {
        text-align: right !important;
    }

    .order-view-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 8px 11px;
        color: white;
        background: var(--fm-primary);
        border-radius: 7px;
        font-size: 11px;
        font-weight: 650;
        text-decoration: none;
        white-space: nowrap;
        transition: background .15s ease;
    }

    .order-view-link:hover {
        color: white;
        background: var(--fm-primary-hover);
    }

    .order-view-link svg {
        width: 14px;
        height: 14px;
    }

    .order-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        padding: 16px 22px;
        border-top: 1px solid var(--fm-border);
    }

    .order-pagination-info,
    .order-page-number {
        color: var(--fm-muted);
        font-size: 12px;
    }

    .order-pagination-info strong {
        color: var(--fm-text);
        font-weight: 650;
    }

    .order-pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .order-page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 11px;
        color: var(--fm-text);
        background: var(--fm-surface);
        border: 1px solid var(--fm-border);
        border-radius: 7px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
    }

    .order-page-link:hover:not(.is-disabled) {
        color: var(--fm-primary-hover);
        background: var(--fm-surface-soft);
    }

    .order-page-link.is-disabled {
        color: var(--fm-muted);
        opacity: .5;
        cursor: not-allowed;
    }

    .order-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 48px 20px;
        text-align: center;
    }

    .order-empty-icon {
        width: 54px;
        height: 54px;
        margin-bottom: 15px;
    }

    .order-empty-state h3 {
        margin: 0;
        color: var(--fm-text);
        font-size: 15px;
        font-weight: 700;
    }

    .order-empty-state p {
        margin: 7px 0 0;
        color: var(--fm-muted);
        font-size: 12px;
    }

    .order-header-actions {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .order-new-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 9px 15px;
        color: #fff;
        background: var(--fm-primary);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: background .2s ease;
    }

    .order-new-button:hover {
        color: #fff;
        background: var(--fm-primary-hover);
    }

    .order-new-button span {
        font-size: 17px;
        line-height: 1;
    }
    .order-filter-form {
        display: grid;
        grid-template-columns: minmax(190px, 2fr) repeat(4, minmax(125px, 1fr)) auto;
        align-items: end;
        gap: 12px;
        padding: 18px 22px;
        background: var(--fm-surface-soft);
        border-bottom: 1px solid var(--fm-border);
    }

    .order-filter-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .order-filter-field label {
        color: var(--fm-muted);
        font-size: 11px;
        font-weight: 600;
    }

    .order-filter-field input,
    .order-filter-field select {
        width: 100%;
        min-width: 0;
        min-height: 39px;
        padding: 8px 10px;
        color: var(--fm-text);
        background: var(--fm-surface);
        border: 1px solid var(--fm-border);
        border-radius: 7px;
        font: inherit;
        font-size: 12px;
    }

    .order-filter-field input:focus,
    .order-filter-field select:focus {
        outline: 2px solid var(--fm-primary);
        outline-offset: 1px;
    }

    .order-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .order-filter-button,
    .order-clear-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 39px;
        padding: 8px 11px;
        border-radius: 7px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .order-filter-button {
        color: #fff;
        background: var(--fm-primary);
        border: 1px solid var(--fm-primary);
    }

    .order-filter-button:hover {
        background: var(--fm-primary-hover);
    }

    .order-clear-button {
        color: var(--fm-text);
        background: var(--fm-surface);
        border: 1px solid var(--fm-border);
    }

    @media (max-width: 1100px) {
        .order-filter-form {
            grid-template-columns: repeat(3, minmax(140px, 1fr));
        }

        .order-search-field {
            grid-column: span 2;
        }
    }

    @media (max-width: 640px) {
        .order-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 15px 17px;
        }

        .order-search-field,
        .order-filter-actions {
            grid-column: 1 / -1;
        }

        .order-filter-button,
        .order-clear-button {
            flex: 1;
        }
    }
    @media (max-width: 640px) {
        .order-management-page {
            gap: 16px;
        }

        .order-overview {
            padding: 18px;
        }

        .order-overview-value {
            font-size: 26px;
        }

        .order-list-heading {
            align-items: flex-start;
            padding: 17px;
        }

        .order-pagination {
            align-items: flex-start;
            padding: 15px 17px;
        }

        .order-pagination-controls {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>
@endpush
