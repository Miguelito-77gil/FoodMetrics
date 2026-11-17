<?php

namespace App\Http\Controllers;

use App\Models\DeliveryDetail;
use App\Models\ForecastRun;
use App\Models\InventoryRecommendation;
use App\Models\User;
use App\Services\SalesForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $range = request()->query('range', '30d');

        if (!in_array($range, ['today', '7d', '30d', 'year'], true)) {
            $range = '30d';
        }

        $now = CarbonImmutable::now();

        [$start, $end] = match ($range) {
            'today' => [$now->startOfDay(), $now],
            '7d' => [$now->subDays(6)->startOfDay(), $now],
            'year' => [$now->startOfYear(), $now],
            default => [$now->subDays(29)->startOfDay(), $now],
        };

        $monthly = $range === 'year';

        $periodKeys = [];
        $cursor = $monthly ? $start->startOfMonth() : $start->startOfDay();
        $lastPeriod = $monthly ? $end->startOfMonth() : $end->startOfDay();

        while ($cursor <= $lastPeriod) {
            $periodKeys[] = $monthly
                ? $cursor->format('Y-m')
                : $cursor->format('Y-m-d');

            $cursor = $monthly
                ? $cursor->addMonth()
                : $cursor->addDay();
        }

        $salesQuery = DB::table('payments')
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end]);

        if ($monthly) {
            $salesQuery->selectRaw(
                "DATE_FORMAT(paid_at, '%Y-%m') as period, SUM(amount) as total"
            );
        } else {
            $salesQuery->selectRaw(
                'DATE(paid_at) as period, SUM(amount) as total'
            );
        }

        $salesByPeriod = $salesQuery
            ->groupBy('period')
            ->pluck('total', 'period');

        $salesTrend = collect($periodKeys)->map(function ($period) use ($salesByPeriod, $monthly) {
            $date = $monthly
                ? CarbonImmutable::createFromFormat('Y-m', $period)
                : CarbonImmutable::createFromFormat('Y-m-d', $period);

            return [
                'key' => $period,
                'label' => $monthly
                    ? $date->format('M')
                    : $date->format('M j'),
                'total' => round((float) ($salesByPeriod[$period] ?? 0), 2),
            ];
        });

        $salesValues = $salesTrend->pluck('total')->all();
        $salesMaximum = max(1, ...$salesValues) * 1.15;
        $salesCount = count($salesValues);

        $salesChartPoints = collect($salesValues)
            ->map(function ($value, $index) use ($salesCount, $salesMaximum) {
                $x = $salesCount > 1
                    ? 24 + ($index / ($salesCount - 1)) * 552
                    : 300;

                $y = 160 - (($value / $salesMaximum) * 135);

                return round($x, 2) . ',' . round($y, 2);
            })
            ->implode(' ');

        $revenue = (float) DB::table('payments')
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $paidOrderCount = (int) DB::table('payments')
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->distinct()
            ->count('order_id');

        $orderQuery = DB::table('orders')
            ->whereBetween('ordered_at', [$start, $end]);

        $totalOrders = (clone $orderQuery)->count();

        $averageCollectedPerPaidOrder = $paidOrderCount > 0
            ? $revenue / $paidOrderCount
            : 0;

        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.ordered_at', [$start, $end])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw(
                'order_items.product_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.subtotal) as total_sales'
            )
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        $bestSellerMaximum = max(
            1,
            (int) $bestSellers->max('total_quantity')
        );

        $statusLabels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'out_for_delivery' => 'Out for Delivery',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $statusColors = [
            'pending' => '#C6A96B',
            'confirmed' => '#8B9B73',
            'preparing' => '#A2AD83',
            'ready' => '#6F805B',
            'out_for_delivery' => '#718A9A',
            'completed' => '#858E62',
            'cancelled' => '#B66F68',
        ];

        $orderStatusCounts = DB::table('orders')
            ->whereBetween('ordered_at', [$start, $end])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusTotal = (int) $orderStatusCounts->sum();

        $orderStatusDistribution = collect($statusLabels)
            ->map(function ($label, $key) use (
                $orderStatusCounts,
                $statusTotal,
                $statusColors
            ) {
                $count = (int) ($orderStatusCounts[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => $count,
                    'percentage' => $statusTotal > 0
                        ? round(($count / $statusTotal) * 100, 2)
                        : 0,
                    'color' => $statusColors[$key],
                ];
            })
            ->values();

        $statuses = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'picked_up' => 'Picked Up',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ];

        $statusCounts = DeliveryDetail::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $deliveryStats = [];

        foreach ($statuses as $key => $label) {
            $deliveryStats[$key] = (int) ($statusCounts[$key] ?? 0);
        }

        $totalDeliveries = array_sum($deliveryStats);

        $statusDistribution = collect($statuses)
            ->map(function ($label, $key) use ($deliveryStats, $totalDeliveries) {
                $count = $deliveryStats[$key];

                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => $count,
                    'percentage' => $totalDeliveries > 0
                        ? round(($count / $totalDeliveries) * 100, 1)
                        : 0,
                ];
            })
            ->values();

        $workload = DeliveryDetail::query()
            ->select(
                'delivery_staff_id',
                'status',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('delivery_staff_id')
            ->groupBy('delivery_staff_id', 'status')
            ->get()
            ->groupBy('delivery_staff_id');

        $deliveryStaff = User::query()
            ->where('role', 'delivery_staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $staff) use ($workload) {
                $records = $workload->get($staff->id, collect());

                $counts = $records->keyBy('status')
                    ->map(fn ($record) => (int) $record->total);

                $staff->setAttribute('delivery_counts', [
                    'assigned' => $counts->get('assigned', 0),
                    'picked_up' => $counts->get('picked_up', 0),
                    'delivered' => $counts->get('delivered', 0),
                    'failed' => $counts->get('failed', 0),
                ]);

                $staff->setAttribute(
                    'active_deliveries',
                    $counts->get('assigned', 0)
                        + $counts->get('picked_up', 0)
                );

                return $staff;
            });

        $latestForecastRuns = ForecastRun::query()
            ->with([
                'salesForecasts' => fn ($query) => $query->orderBy('forecast_date'),
                'inventoryRecommendations.ingredient',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $pendingInventoryRecommendations = InventoryRecommendation::query()
            ->with(['ingredient', 'forecastRun'])
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('expected_stockout_date')
            ->latest('id')
            ->limit(50)
            ->get();

        $pendingInventoryRecommendationCount = InventoryRecommendation::query()
            ->where('status', 'pending')
            ->count();

        return view('admin.dashboard', [
            'latestForecastRuns' => $latestForecastRuns,
            'pendingInventoryRecommendations' => $pendingInventoryRecommendations,
            'pendingInventoryRecommendationCount' => $pendingInventoryRecommendationCount,
            'user' => request()->user(),
            'range' => $range,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'revenue' => $revenue,
            'totalOrders' => $totalOrders,
            'paidOrderCount' => $paidOrderCount,
            'averageCollectedPerPaidOrder' => $averageCollectedPerPaidOrder,
            'salesTrend' => $salesTrend,
            'salesChartPoints' => $salesChartPoints,
            'salesMaximum' => $salesMaximum,
            'bestSellers' => $bestSellers,
            'bestSellerMaximum' => $bestSellerMaximum,
            'orderStatusDistribution' => $orderStatusDistribution,
            'orderStatusTotal' => $statusTotal,
            'totalDeliveries' => $totalDeliveries,
            'deliveryStats' => $deliveryStats,
            'statusDistribution' => $statusDistribution,
            'deliveryStaff' => $deliveryStaff,
        ]);
    }

    public function approveInventoryRecommendation(
        Request $request,
        InventoryRecommendation $recommendation
    ): RedirectResponse {
        return $this->reviewInventoryRecommendation(
            $request,
            $recommendation,
            'approved'
        );
    }

    public function dismissInventoryRecommendation(
        Request $request,
        InventoryRecommendation $recommendation
    ): RedirectResponse {
        return $this->reviewInventoryRecommendation(
            $request,
            $recommendation,
            'dismissed'
        );
    }

    private function reviewInventoryRecommendation(
        Request $request,
        InventoryRecommendation $recommendation,
        string $status
    ): RedirectResponse {
        DB::transaction(function () use ($request, $recommendation, $status) {
            $lockedRecommendation = InventoryRecommendation::query()
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRecommendation->status !== 'pending') {
                abort(409, 'This recommendation has already been reviewed.');
            }

            if (
                $status === 'approved'
                && (float) $lockedRecommendation->recommended_quantity <= 0
            ) {
                abort(422, 'A recommendation with no replenishment quantity cannot be approved.');
            }

            $lockedRecommendation->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        $message = $status === 'approved'
            ? 'Inventory recommendation approved. No purchase order or stock movement was created.'
            : 'Inventory recommendation dismissed.';

        return redirect()
            ->route('admin.index')
            ->with('success', $message);
    }

    public function generateForecast(
        Request $request,
        SalesForecastService $forecastService
    ): RedirectResponse {
        $validated = $request->validate([
            'forecast_type' => ['required', 'in:sales,revenue'],
            'forecast_start_date' => ['required', 'date', 'after:today'],
            'forecast_end_date' => [
                'required',
                'date',
                'after_or_equal:forecast_start_date',
            ],
            'lookback_days' => ['required', 'integer', 'between:7,730'],
        ]);

        $run = $forecastService->generate(
            $validated['forecast_type'],
            $validated['forecast_start_date'],
            $validated['forecast_end_date'],
            $request->user()->id,
            (int) $validated['lookback_days']
        );

        return redirect()
            ->route('admin.index')
            ->with(
                'success',
                ucfirst($run->forecast_type) . ' forecast generated successfully.'
            );
    }
}