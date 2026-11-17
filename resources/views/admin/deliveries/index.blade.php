@extends('layouts.app')

@section('title', 'Delivery Assignments')
@section('breadcrumb', 'Delivery Assignments')

@section('content')
    <section class="fm-page-header">
        <div>
            <h1 class="fm-page-title">Delivery Assignments</h1>
            <p class="fm-page-description">
                Review pending deliveries and assign them to active delivery staff.
            </p>
        </div>

        <div class="fm-actions">
            <a href="{{ route('admin.index') }}" class="fm-btn fm-btn-secondary">
                Back to Admin Dashboard
            </a>
        </div>
    </section>


    <section class="fm-grid fm-grid-2">
        <article class="fm-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Pending Assignments</span>
                <span class="fm-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($deliveries->total()) }}</div>
            <p class="fm-stat-note">Deliveries waiting for an assigned driver</p>
        </article>

        <article class="fm-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Available Delivery Staff</span>
                <span class="fm-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" aria-hidden="true">
                        <circle cx="9" cy="8" r="3"/>
                        <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
                        <path d="M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($deliveryStaff->count()) }}</div>
            <p class="fm-stat-note">Active accounts eligible for assignment</p>
        </article>
    </section>

    <section class="fm-card" style="margin-top: 20px;">
        <div class="fm-page-header">
            <div>
                <h2 class="fm-card-title">Pending and Unassigned Deliveries</h2>
                <p class="fm-page-description">
                    Only pending deliveries without an assigned driver are displayed.
                </p>
            </div>
            <span class="fm-badge fm-badge-warning">
                {{ $deliveries->total() }} pending
            </span>
        </div>

        @if($deliveries->isEmpty())
            <div class="fm-empty">
                <strong>No deliveries waiting for assignment</strong>
                <p>
                    There are currently no pending and unassigned delivery records.
                    New eligible deliveries will appear here automatically.
                </p>
            </div>
        @else
            <div class="fm-delivery-list">
                @foreach($deliveries as $delivery)
                    <article class="fm-delivery-assignment">
                        <div class="fm-delivery-assignment-header">
                            <div>
                                <span class="fm-stat-label">Order Number</span>
                                <h3 class="fm-delivery-order-number">
                                    {{ $delivery->order->order_number }}
                                </h3>
                            </div>

                            <span class="fm-badge fm-badge-warning">
                                Pending assignment
                            </span>
                        </div>

                        <div class="fm-delivery-details">
                            <div>
                                <span class="fm-detail-label">Recipient</span>
                                <span class="fm-detail-value">
                                    {{ $delivery->recipient_name }}
                                </span>
                            </div>

                            <div>
                                <span class="fm-detail-label">Phone</span>
                                <span class="fm-detail-value">
                                    {{ $delivery->recipient_phone }}
                                </span>
                            </div>

                            <div>
                                <span class="fm-detail-label">Delivery Address</span>
                                <span class="fm-detail-value">
                                    {{ $delivery->delivery_address }}
                                </span>
                            </div>

                            <div>
                                <span class="fm-detail-label">Delivery Fee</span>
                                <span class="fm-detail-value">
                                    PHP {{ number_format((float) $delivery->delivery_fee, 2) }}
                                </span>
                            </div>

                            <div>
                                <span class="fm-detail-label">Order Total</span>
                                <span class="fm-detail-value">
                                    PHP {{ number_format((float) $delivery->order->total_amount, 2) }}
                                </span>
                            </div>

                            <div>
                                <span class="fm-detail-label">Instructions</span>
                                <span class="fm-detail-value">
                                    {{ $delivery->delivery_instructions ?: 'None' }}
                                </span>
                            </div>
                        </div>

                        <form method="POST"
                              action="{{ route('admin.deliveries.assign', $delivery) }}"
                              class="fm-assignment-form">
                            @csrf

                            <h4 class="fm-assignment-form-title">Assign Delivery Staff</h4>

                            <div class="fm-field">
                                <label class="fm-label" for="staff-{{ $delivery->id }}">
                                    Select Active Delivery Staff
                                </label>

                                <select
                                    id="staff-{{ $delivery->id }}"
                                    name="delivery_staff_id"
                                    class="fm-select"
                                    required
                                >
                                    <option value="">Select delivery staff</option>

                                    @foreach($deliveryStaff as $staff)
                                        <option
                                            value="{{ $staff->id }}"
                                            @selected((string) old('delivery_staff_id') === (string) $staff->id)
                                        >
                                            {{ $staff->name }} — {{ $staff->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fm-field">
                                <label class="fm-label" for="remarks-{{ $delivery->id }}">
                                    Assignment Remarks
                                    <span class="fm-muted">(Optional)</span>
                                </label>

                                <textarea
                                    id="remarks-{{ $delivery->id }}"
                                    name="remarks"
                                    class="fm-textarea"
                                    maxlength="1000"
                                    rows="3"
                                    placeholder="Add instructions or assignment remarks..."
                                >{{ old('remarks') }}</textarea>
                            </div>

                            <div class="fm-actions">
                                <button
                                    type="submit"
                                    class="fm-btn"
                                    @disabled($deliveryStaff->isEmpty())
                                >
                                    Assign Delivery
                                </button>
                            </div>

                            @if($deliveryStaff->isEmpty())
                                <p class="fm-muted">
                                    No active delivery staff accounts are available.
                                    Activate a delivery staff account before assigning this delivery.
                                </p>
                            @endif
                        </form>
                    </article>
                @endforeach
            </div>

            <div class="fm-pagination">
                {{ $deliveries->links() }}
            </div>
        @endif
    </section>

    <p class="fm-muted fm-assignment-note">
        Assignment is restricted to administrators. Every successful assignment
        updates the delivery status and records its history.
    </p>
@endsection

@push('head')
    <style>
        .fm-delivery-list {
            display: grid;
            gap: 18px;
        }

        .fm-delivery-assignment {
            padding: 20px;
            border: 1px solid var(--fm-border);
            border-radius: 12px;
            background: var(--fm-surface);
        }

        .fm-delivery-assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .fm-delivery-order-number {
            margin: 5px 0 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--fm-text);
        }

        .fm-delivery-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 18px 0;
            border-top: 1px solid var(--fm-border);
            border-bottom: 1px solid var(--fm-border);
        }

        .fm-detail-label {
            display: block;
            margin-bottom: 6px;
            color: var(--fm-muted);
            font-size: 12px;
        }

        .fm-detail-value {
            display: block;
            color: var(--fm-text);
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .fm-assignment-form {
            display: grid;
            gap: 15px;
            padding-top: 20px;
        }

        .fm-assignment-form-title {
            margin: 0;
            color: var(--fm-text);
            font-size: 15px;
            font-weight: 700;
        }

        .fm-assignment-note {
            margin-top: 18px;
            line-height: 1.6;
        }

        .fm-pagination {
            margin-top: 22px;
            overflow-x: auto;
        }

        @media (max-width: 640px) {
            .fm-delivery-details {
                grid-template-columns: 1fr;
            }

            .fm-delivery-assignment {
                padding: 15px;
            }
        }
    </style>
@endpush