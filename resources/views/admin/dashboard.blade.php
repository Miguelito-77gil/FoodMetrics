@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('breadcrumb', 'Admin Dashboard')

@section('content')
    <section class="fm-page-header">
        <div>
            <h1 class="fm-page-title">Admin Dashboard</h1>
            <p class="fm-page-description">
                Welcome, {{ $user->name }}. Here is the current delivery overview.
            </p>
        </div>

        <div class="fm-actions">
            <a href="{{ route('orders.index') }}" class="fm-btn fm-btn-secondary">
                View Orders
            </a>

            <a href="{{ route('cashier.orders.create') }}" class="fm-btn fm-btn-secondary">
                New Order
            </a>

            <a href="{{ route('admin.deliveries.index') }}" class="fm-btn">
                Manage Assignments
            </a>
        </div>
    </section>

    <section class="fm-analytics-section">
        <div class="fm-analytics-heading">
            <div>
                <h2 class="fm-card-title">Business Analytics</h2>
                <p class="fm-muted fm-section-description">
                    Sales, product performance, and order activity from recorded transactions.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.index') }}" class="fm-analytics-filter">
                <label for="analytics-range">Date range</label>
                <select id="analytics-range" name="range" class="fm-select">
                    <option value="today" @selected($range === 'today')>Today</option>
                    <option value="7d" @selected($range === '7d')>Last 7 Days</option>
                    <option value="30d" @selected($range === '30d')>Last 30 Days</option>
                    <option value="year" @selected($range === 'year')>This Year</option>
                </select>
                <button type="submit" class="fm-btn fm-btn-secondary">Apply</button>
            </form>
        </div>

        <div class="fm-grid fm-grid-4 fm-analytics-kpis">
            <article class="fm-card fm-analytics-kpi">
                <span class="fm-stat-label">Collected Revenue</span>
                <strong class="fm-analytics-value">{{ "\u{20B1}" }}{{ number_format($revenue, 2) }}</strong>
                <span class="fm-muted">Completed payments only</span>
            </article>

            <article class="fm-card fm-analytics-kpi">
                <span class="fm-stat-label">Total Orders</span>
                <strong class="fm-analytics-value">{{ number_format($totalOrders) }}</strong>
                <span class="fm-muted">Orders created in this period</span>
            </article>

            <article class="fm-card fm-analytics-kpi">
                <span class="fm-stat-label">Paid Orders</span>
                <strong class="fm-analytics-value">{{ number_format($paidOrderCount) }}</strong>
                <span class="fm-muted">Orders with completed payments</span>
            </article>

            <article class="fm-card fm-analytics-kpi">
                <span class="fm-stat-label">Average Collected / Paid Order</span>
                <strong class="fm-analytics-value">{{ "\u{20B1}" }}{{ number_format($averageCollectedPerPaidOrder, 2) }}</strong>
                <span class="fm-muted">Collected revenue divided by paid orders</span>
            </article>
        </div>

        <div class="fm-analytics-grid">
            <article class="fm-card fm-analytics-panel fm-sales-panel">
                <div class="fm-section-heading">
                    <div>
                        <h3 class="fm-card-title">Sales Trend</h3>
                        <p class="fm-muted fm-section-description">
                            Collected payments over the selected period
                        </p>
                    </div>
                    <span class="fm-badge">Revenue</span>
                </div>

                <div class="fm-chart-summary">
                    <strong>{{ "\u{20B1}" }}{{ number_format($revenue, 2) }}</strong>
                    <span class="fm-muted">Total collected</span>
                </div>

                <div class="fm-line-chart-wrap">
                    <svg class="fm-line-chart" viewBox="0 0 600 190" role="img" aria-label="Sales trend line chart">
                        <defs>
                            <linearGradient id="fm-sales-area" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--fm-primary)" stop-opacity=".25"/>
                                <stop offset="100%" stop-color="var(--fm-primary)" stop-opacity=".015"/>
                            </linearGradient>
                        </defs>
                        <line x1="24" y1="25" x2="576" y2="25" class="fm-chart-gridline"/>
                        <line x1="24" y1="70" x2="576" y2="70" class="fm-chart-gridline"/>
                        <line x1="24" y1="115" x2="576" y2="115" class="fm-chart-gridline"/>
                        <line x1="24" y1="160" x2="576" y2="160" class="fm-chart-gridline"/>

                        @if($salesTrend->sum('total') > 0)
                            <polygon
                                points="24,160 {{ $salesChartPoints }} 576,160"
                                class="fm-chart-area"
                            />
                            <polyline
                                points="{{ $salesChartPoints }}"
                                class="fm-chart-line"
                            />
                            @foreach($salesTrend as $point)
                                @php
                                    $pointX = $salesTrend->count() > 1
                                        ? 24 + ($loop->index / ($salesTrend->count() - 1)) * 552
                                        : 300;
                                    $pointY = 160 - (($point['total'] / $salesMaximum) * 135);
                                @endphp
                                <circle
                                    cx="{{ $pointX }}"
                                    cy="{{ $pointY }}"
                                    r="3.5"
                                    class="fm-chart-point"
                                >
                                    <title>{{ $point['label'] }}: {{ "\u{20B1}" }}{{ number_format($point['total'], 2) }}</title>
                                </circle>
                            @endforeach
                        @else
                            <text x="300" y="95" text-anchor="middle" class="fm-chart-empty-text">
                                No collected sales in this period
                            </text>
                        @endif
                    </svg>
                </div>

                <div class="fm-chart-labels">
                    @foreach($salesTrend as $point)
                        @if($loop->first || $loop->last || ($loop->index % max(1, (int) ceil($salesTrend->count() / 6)) === 0))
                            <span>{{ $point['label'] }}</span>
                        @endif
                    @endforeach
                </div>
            </article>

            <article class="fm-card fm-analytics-panel">
                <div class="fm-section-heading">
                    <div>
                        <h3 class="fm-card-title">Order Status</h3>
                        <p class="fm-muted fm-section-description">
                            Order distribution for the selected period
                        </p>
                    </div>
                    <span class="fm-badge">{{ number_format($orderStatusTotal) }} orders</span>
                </div>

                <div class="fm-donut-layout">
                    <div class="fm-donut-wrap">
                        <svg class="fm-donut" viewBox="0 0 42 42" role="img" aria-label="Order status distribution chart">
                            <circle class="fm-donut-track" cx="21" cy="21" r="15.9155"/>
                            @php $donutOffset = 0; @endphp
                            @foreach($orderStatusDistribution as $status)
                                @if($status['percentage'] > 0)
                                    <circle
                                        cx="21"
                                        cy="21"
                                        r="15.9155"
                                        fill="none"
                                        stroke="{{ $status['color'] }}"
                                        stroke-width="5"
                                        stroke-dasharray="{{ $status['percentage'] }} {{ 100 - $status['percentage'] }}"
                                        stroke-dashoffset="{{ -$donutOffset }}"
                                        transform="rotate(-90 21 21)"
                                    />
                                    @php $donutOffset += $status['percentage']; @endphp
                                @endif
                            @endforeach
                            <text x="21" y="20" class="fm-donut-total">{{ number_format($orderStatusTotal) }}</text>
                            <text x="21" y="25" class="fm-donut-caption">Orders</text>
                        </svg>
                    </div>

                    <div class="fm-donut-legend">
                        @foreach($orderStatusDistribution as $status)
                            <div class="fm-donut-legend-row">
                                <span class="fm-donut-key" style="background: {{ $status['color'] }}"></span>
                                <span>{{ $status['label'] }}</span>
                                <strong>{{ number_format($status['count']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="fm-card fm-analytics-panel fm-best-sellers-panel">
                <div class="fm-section-heading">
                    <div>
                        <h3 class="fm-card-title">Best-Selling Products</h3>
                        <p class="fm-muted fm-section-description">
                            Ranked by units sold, excluding cancelled orders
                        </p>
                    </div>
                </div>

                @forelse($bestSellers as $product)
                    <div class="fm-product-rank">
                        <div class="fm-product-rank-heading">
                            <strong>{{ $product->product_name }}</strong>
                            <span>{{ number_format($product->total_quantity) }} sold</span>
                        </div>
                        <div class="fm-bar-track">
                            <div
                                class="fm-bar-fill fm-product-bar"
                                style="width: {{ ($product->total_quantity / $bestSellerMaximum) * 100 }}%"
                            ></div>
                        </div>
                        <span class="fm-product-sales">
                            {{ "\u{20B1}" }}{{ number_format((float) $product->total_sales, 2) }} sales
                        </span>
                    </div>
                @empty
                    <div class="fm-empty">
                        No product sales recorded in this period.
                    </div>
                @endforelse
            </article>
        </div>
    </section>
    <section class="fm-analytics-section fm-forecast-section">
        <div class="fm-analytics-heading">
            <div>
                <h2 class="fm-card-title">Predictive Analytics</h2>
                <p class="fm-muted fm-section-description">
                    Generate daily sales and revenue forecasts using historical records.
                </p>
            </div>
            <span class="fm-badge">Weekday Average Baseline</span>
        </div>

        <div class="fm-forecast-layout">
            <article class="fm-card fm-forecast-form-card">
                <div class="fm-section-heading">
                    <div>
                        <h3 class="fm-card-title">Generate Forecast</h3>
                        <p class="fm-muted fm-section-description">
                            Configure the forecast period and historical lookback.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.forecasts.generate') }}" class="fm-forecast-form">
                    @csrf

                    <div class="fm-form-group">
                        <label for="forecast_type" class="fm-label">Forecast Type</label>
                        <select id="forecast_type" name="forecast_type" class="fm-select" required>
                            <option value="sales" @selected(old('forecast_type', 'sales') === 'sales')>Sales</option>
                            <option value="revenue" @selected(old('forecast_type') === 'revenue')>Revenue</option>
                        </select>
                        @error('forecast_type')
                            <span class="fm-form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-forecast-form-grid">
                        <div class="fm-form-group">
                            <label for="forecast_start_date" class="fm-label">Forecast Start</label>
                            <input
                                type="date"
                                id="forecast_start_date"
                                name="forecast_start_date"
                                class="fm-input"
                                min="{{ now()->addDay()->toDateString() }}"
                                value="{{ old('forecast_start_date', now()->addDay()->toDateString()) }}"
                                required
                            >
                            @error('forecast_start_date')
                                <span class="fm-form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="fm-form-group">
                            <label for="forecast_end_date" class="fm-label">Forecast End</label>
                            <input
                                type="date"
                                id="forecast_end_date"
                                name="forecast_end_date"
                                class="fm-input"
                                min="{{ now()->addDay()->toDateString() }}"
                                value="{{ old('forecast_end_date', now()->addDays(7)->toDateString()) }}"
                                required
                            >
                            @error('forecast_end_date')
                                <span class="fm-form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="fm-form-group">
                        <label for="lookback_days" class="fm-label">Historical Lookback</label>
                        <select id="lookback_days" name="lookback_days" class="fm-select" required>
                            <option value="30" @selected((string) old('lookback_days', '365') === '30')>30 days</option>
                            <option value="90" @selected((string) old('lookback_days', '365') === '90')>90 days</option>
                            <option value="180" @selected((string) old('lookback_days', '365') === '180')>180 days</option>
                            <option value="365" @selected((string) old('lookback_days', '365') === '365')>365 days</option>
                            <option value="730" @selected((string) old('lookback_days', '365') === '730')>730 days</option>
                        </select>
                        @error('lookback_days')
                            <span class="fm-form-error">{{ $message }}</span>
                        @enderror
                        <span class="fm-muted fm-forecast-help">
                            The model compares historical totals for each day of the week.
                        </span>
                    </div>

                    <button type="submit" class="fm-btn fm-forecast-submit">
                        Generate Forecast
                    </button>
                </form>
            </article>

            <div class="fm-forecast-results">
                <div class="fm-section-heading">
                    <div>
                        <h3 class="fm-card-title">Latest Forecast Runs</h3>
                        <p class="fm-muted fm-section-description">
                            Recent generated forecasts and their daily predictions.
                        </p>
                    </div>
                    <span class="fm-badge">{{ $latestForecastRuns->count() }} runs</span>
                </div>

                @forelse($latestForecastRuns as $run)
                    <article class="fm-card fm-forecast-run">
                        <div class="fm-forecast-run-heading">
                            <div>
                                <h4>{{ $run->forecast_name }}</h4>
                                <span class="fm-muted">
                                    {{ ucfirst($run->forecast_type) }} · {{ $run->model_name }}
                                </span>
                            </div>

                            @if($run->status === 'completed')
                                <span class="fm-badge fm-badge-success">Completed</span>
                            @elseif($run->status === 'failed')
                                <span class="fm-badge fm-badge-danger">Failed</span>
                            @else
                                <span class="fm-badge fm-badge-warning">{{ ucfirst($run->status) }}</span>
                            @endif
                        </div>

                        <div class="fm-forecast-run-meta">
                            <span>
                                Training:
                                <strong>
                                    {{ $run->training_start_date?->format('M d, Y') ?? 'N/A' }}
                                    –
                                    {{ $run->training_end_date?->format('M d, Y') ?? 'N/A' }}
                                </strong>
                            </span>
                            <span>
                                Forecast:
                                <strong>
                                    {{ $run->forecast_start_date->format('M d, Y') }}
                                    –
                                    {{ $run->forecast_end_date->format('M d, Y') }}
                                </strong>
                            </span>
                        </div>

                        @if($run->forecast_type === 'inventory')
                            <div class="fm-empty fm-forecast-empty">
                                {{ $run->inventoryRecommendations->count() }}
                                ingredient recommendation(s) generated for this run.
                            </div>
                        @elseif($run->salesForecasts->isNotEmpty())
                            <div class="fm-forecast-record-list">
                                @foreach($run->salesForecasts->take(7) as $forecast)
                                    <div class="fm-forecast-record">
                                        <span>{{ $forecast->forecast_date->format('M d, Y') }}</span>
                                        <strong>{{ "\u{20B1}" }}{{ number_format((float) $forecast->predicted_sales, 2) }}</strong>
                                    </div>
                                @endforeach
                            </div>

                            @if($run->salesForecasts->count() > 7)
                                <p class="fm-muted fm-forecast-more">
                                    Showing 7 of {{ $run->salesForecasts->count() }} forecast dates.
                                </p>
                            @endif
                        @else
                            <div class="fm-empty fm-forecast-empty">
                                No daily forecast records available for this run.
                            </div>
                        @endif

                        @if($run->completed_at)
                            <p class="fm-muted fm-forecast-generated">
                                Generated {{ $run->completed_at->format('M d, Y h:i A') }}
                            </p>
                        @endif
                    </article>
                @empty
                    <div class="fm-card fm-empty">
                        No forecast runs yet. Generate your first sales or revenue forecast.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="fm-forecast-notice">
            <strong>Forecast interpretation</strong>
            <p>
                These predictions use historical records and a weekday-average baseline.
                The project's historical dataset includes simulated or sample data.
                Forecasts are exploratory estimates, not guaranteed outcomes or validated
                predictions. Actual values and prediction accuracy have not yet been evaluated.
            </p>
        </div>
    </section>
    <!-- ADMIN INVENTORY RECOMMENDATIONS -->
    <section class="fm-analytics-section fm-recommendation-section">
        <div class="fm-analytics-heading">
            <div>
                <h2 class="fm-card-title">Inventory Recommendations</h2>
                <p class="fm-muted fm-section-description">
                    Review suggested ingredient replenishment based on recorded historical consumption.
                </p>
            </div>

            <span class="fm-badge">
                {{ number_format($pendingInventoryRecommendationCount) }} pending review
            </span>
        </div>

        <div class="fm-recommendation-notice">
            <strong>Review before taking action</strong>
            <p>
                These recommendations use historical records that may include simulated or sample data.
                Ingredients with insufficient consumption history or no active menu usage are flagged
                for manual review. Approval only records your decision; it does not create a purchase
                order, contact a supplier, or change inventory stock.
            </p>
        </div>

        <div class="fm-recommendation-list">
            @forelse($pendingInventoryRecommendations as $recommendation)
                @php
                    $requiresManualReview = str_contains(
                        strtolower($recommendation->reason ?? ''),
                        'manual review'
                    );
                    $priorityClass = 'fm-recommendation-priority-' . $recommendation->priority;
                @endphp

                <article class="fm-card fm-recommendation-mini-card">
    <div class="fm-recommendation-mini-heading">
        <div class="fm-recommendation-mini-title">
            <h3>{{ $recommendation->ingredient?->name ?? 'Unknown ingredient' }}</h3>
            <span class="fm-recommendation-priority {{ $priorityClass }}">
                {{ ucfirst($recommendation->priority) }}
            </span>
        </div>
        <span class="fm-recommendation-mini-status">Pending</span>
    </div>

    @if($requiresManualReview)
        <div>
            <span class="fm-badge fm-badge-warning">Manual review</span>
        </div>
    @endif

    <div class="fm-recommendation-mini-info">
        <div>
            <span>Current stock</span>
            <strong>
                {{ number_format((float) $recommendation->current_stock, 2) }}
                {{ $recommendation->ingredient?->base_unit }}
            </strong>
        </div>
        <div>
            <span>Suggested quantity</span>
            <strong>
                {{ number_format((float) $recommendation->recommended_quantity, 2) }}
                {{ $recommendation->ingredient?->base_unit }}
            </strong>
        </div>
    </div>

    <div class="fm-recommendation-mini-actions">
        <form method="POST" action="{{ route('admin.inventory-recommendations.approve', $recommendation) }}">
            @csrf
            <button
                type="submit"
                class="fm-btn fm-recommendation-approve"
                @disabled((float) $recommendation->recommended_quantity <= 0)
            >
                Approve
            </button>
        </form>

        <form method="POST" action="{{ route('admin.inventory-recommendations.dismiss', $recommendation) }}">
            @csrf
            <button type="submit" class="fm-btn fm-btn-secondary">
                Dismiss
            </button>
        </form>
    </div>
</article>
            @empty
                <div class="fm-card fm-recommendation-empty">
                    <strong>No pending inventory recommendations</strong>
                    <p class="fm-muted">
                        Newly generated recommendations that require review will appear here.
                    </p>
                </div>
            @endforelse
        </div>
    </section>
    <section class="fm-grid fm-grid-4 fm-admin-stats">
        <article class="fm-card fm-stat-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Total Deliveries</span>
                <span class="fm-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3 6h12v12H3z"/>
                        <path d="M15 10h4l3 4v4h-7z"/>
                        <circle cx="7.5" cy="18" r="1.8"/>
                        <circle cx="18.5" cy="18" r="1.8"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($totalDeliveries) }}</div>
            <p class="fm-stat-note">All recorded delivery transactions</p>
        </article>

        <article class="fm-card fm-stat-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Pending</span>
                <span class="fm-stat-icon fm-stat-icon-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($deliveryStats['pending']) }}</div>
            <p class="fm-stat-note">Waiting for assignment</p>
        </article>

        <article class="fm-card fm-stat-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Assigned</span>
                <span class="fm-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($deliveryStats['assigned']) }}</div>
            <p class="fm-stat-note">Waiting for pickup</p>
        </article>

        <article class="fm-card fm-stat-card">
            <div class="fm-stat-top">
                <span class="fm-stat-label">Picked Up</span>
                <span class="fm-stat-icon fm-stat-icon-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3 12h13"/>
                        <path d="m11 6 6 6-6 6"/>
                        <path d="M19 5h2v14h-2"/>
                    </svg>
                </span>
            </div>
            <div class="fm-stat-value">{{ number_format($deliveryStats['picked_up']) }}</div>
            <p class="fm-stat-note">Currently in transit</p>
        </article>
    </section>

    <section class="fm-admin-content-grid">
        <article class="fm-card fm-admin-panel">
            <div class="fm-section-heading">
                <div>
                    <h2 class="fm-card-title">Delivery Status Distribution</h2>
                    <p class="fm-muted fm-section-description">
                        Breakdown based on current delivery record statuses.
                    </p>
                </div>
                <span class="fm-badge">All deliveries</span>
            </div>

            <div class="fm-distribution-list">
                @foreach($statusDistribution as $status)
                    <div class="fm-distribution-row">
                        <div class="fm-distribution-heading">
                            <span>{{ $status['label'] }}</span>
                            <strong>
                                {{ number_format($status['count']) }}
                                <span class="fm-muted">
                                    ({{ $status['percentage'] }}%)
                                </span>
                            </strong>
                        </div>

                        <div class="fm-bar-track">
                            <div
                                class="fm-bar-fill fm-status-{{ str_replace('_', '-', $status['key']) }}"
                                style="width: {{ $status['percentage'] }}%"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="fm-status-summary">
                <span>Failed: <strong>{{ number_format($deliveryStats['failed']) }}</strong></span>
                <span>Cancelled: <strong>{{ number_format($deliveryStats['cancelled']) }}</strong></span>
                <span>Delivered: <strong>{{ number_format($deliveryStats['delivered']) }}</strong></span>
            </div>
        </article>

        <article class="fm-card fm-admin-panel">
            <div class="fm-section-heading">
                <div>
                    <h2 class="fm-card-title">Delivery Staff Workload</h2>
                    <p class="fm-muted fm-section-description">
                        Delivery activity grouped by active delivery staff.
                    </p>
                </div>
                <span class="fm-badge">{{ $deliveryStaff->count() }} staff</span>
            </div>

            <div class="fm-staff-list">
                @forelse($deliveryStaff as $staff)
                    <div class="fm-staff-card">
                        <div class="fm-staff-heading">
                            <div class="fm-staff-avatar">
                                {{ strtoupper(substr($staff->name, 0, 1)) }}
                            </div>
                            <div class="fm-staff-details">
                                <strong>{{ $staff->name }}</strong>
                                <span>{{ $staff->email }}</span>
                            </div>
                            <span class="fm-badge fm-badge-success">
                                Active deliveries: {{ $staff->active_deliveries }}
                            </span>
                        </div>

                        <div class="fm-staff-metrics">
                            <div class="fm-staff-metric">
                                <strong>{{ $staff->delivery_counts['assigned'] }}</strong>
                                <span>Assigned</span>
                            </div>

                            <div class="fm-staff-metric">
                                <strong>{{ $staff->delivery_counts['picked_up'] }}</strong>
                                <span>Picked Up</span>
                            </div>

                            <div class="fm-staff-metric">
                                <strong>{{ $staff->delivery_counts['delivered'] }}</strong>
                                <span>Delivered</span>
                            </div>

                            <div class="fm-staff-metric">
                                <strong>{{ $staff->delivery_counts['failed'] }}</strong>
                                <span>Failed</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="fm-empty">
                        No active delivery staff accounts found.
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="fm-card fm-admin-quick-link">
        <div>
            <h2 class="fm-card-title">Delivery Assignment Management</h2>
            <p class="fm-muted">
                Review pending deliveries and assign them to available delivery staff.
            </p>
        </div>

        <a href="{{ route('admin.deliveries.index') }}" class="fm-btn">
            Open Assignments
        </a>
    </section>
@endsection