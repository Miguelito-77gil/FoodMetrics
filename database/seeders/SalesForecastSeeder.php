<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesForecastSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            if (DB::table('sales_forecasts')->exists()) {
                throw new RuntimeException(
                    'Sales forecast records already exist. Seeder stopped to protect existing data.'
                );
            }

            $runs = DB::table('forecast_runs')
                ->whereIn('forecast_type', ['sales', 'revenue'])
                ->get()
                ->keyBy('forecast_type');

            foreach (['sales', 'revenue'] as $type) {
                if (!$runs->has($type)) {
                    throw new RuntimeException(
                        "Missing {$type} forecast run."
                    );
                }

                $run = $runs->get($type);

                if (
                    $run->status !== 'pending' ||
                    $run->forecast_start_date !== '2026-11-01' ||
                    $run->forecast_end_date !== '2026-11-30'
                ) {
                    throw new RuntimeException(
                        "The {$type} forecast run has an unexpected status or date range."
                    );
                }
            }

            $historicalStart = Carbon::parse('2025-11-01');
            $historicalEnd = Carbon::parse('2026-10-31');

            $dailyRevenue = DB::table('orders')
                ->where('status', 'completed')
                ->whereBetween('ordered_at', [
                    $historicalStart->startOfDay(),
                    $historicalEnd->copy()->endOfDay(),
                ])
                ->selectRaw('
                    DAYOFWEEK(ordered_at) AS weekday_number,
                    SUM(total_amount) AS total_revenue,
                    COUNT(*) AS completed_orders
                ')
                ->groupByRaw('DAYOFWEEK(ordered_at)')
                ->get()
                ->keyBy('weekday_number');

            $weekdayCounts = array_fill(1, 7, 0);

            for (
                $date = $historicalStart->copy();
                $date->lte($historicalEnd);
                $date->addDay()
            ) {
                $weekdayNumber = (int) $date->dayOfWeek + 1;
                $weekdayCounts[$weekdayNumber]++;
            }

            $weekdayAverages = [];

            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $totalRevenue = (float) (
                    $dailyRevenue->get($weekday)->total_revenue ?? 0
                );

                if ($weekdayCounts[$weekday] <= 0) {
                    throw new RuntimeException(
                        "No historical calendar days found for weekday {$weekday}."
                    );
                }

                $weekdayAverages[$weekday] =
                    $totalRevenue / $weekdayCounts[$weekday];
            }

            // Weighted average of the latest three completed historical months.
            // Weights: October 50%, September 30%, August 20%.
            $monthlyRevenue = DB::table('orders')
                ->where('status', 'completed')
                ->whereBetween('ordered_at', [
                    '2026-08-01 00:00:00',
                    '2026-10-31 23:59:59',
                ])
                ->selectRaw('
                    DATE_FORMAT(ordered_at, "%Y-%m") AS sales_month,
                    SUM(total_amount) AS total_revenue
                ')
                ->groupByRaw('DATE_FORMAT(ordered_at, "%Y-%m")')
                ->get()
                ->keyBy('sales_month');

            $weightedMonths = [
                '2026-10' => 0.50,
                '2026-09' => 0.30,
                '2026-08' => 0.20,
            ];

            $weightedDailyRevenue = 0;

            foreach ($weightedMonths as $month => $weight) {
                $monthRecord = $monthlyRevenue->get($month);

                if (!$monthRecord) {
                    throw new RuntimeException(
                        "Missing historical revenue for {$month}."
                    );
                }

                $daysInMonth = Carbon::createFromFormat(
                    'Y-m-d',
                    $month . '-01'
                )->daysInMonth;

                $monthlyDailyAverage =
                    (float) $monthRecord->total_revenue / $daysInMonth;

                $weightedDailyRevenue +=
                    $monthlyDailyAverage * $weight;
            }

            if ($weightedDailyRevenue <= 0) {
                throw new RuntimeException(
                    'Weighted historical daily revenue must be positive.'
                );
            }

            $forecastStart = Carbon::parse('2026-11-01');
            $forecastEnd = Carbon::parse('2026-11-30');

            $now = now();
            $salesRecords = [];
            $revenueRecords = [];

            for (
                $date = $forecastStart->copy();
                $date->lte($forecastEnd);
                $date->addDay()
            ) {
                $forecastDate = $date->toDateString();
                $weekdayNumber = (int) $date->dayOfWeek + 1;

                // Sales model: average revenue per calendar weekday,
                // including historical days with zero completed sales.
                $predictedSales = round(
                    $weekdayAverages[$weekdayNumber],
                    2
                );

                // Revenue model: weighted average daily revenue
                // from August, September, and October 2026.
                $predictedRevenue = round(
                    $weightedDailyRevenue,
                    2
                );

                $salesRecords[] = [
                    'forecast_run_id' => $runs->get('sales')->id,
                    'forecast_date' => $forecastDate,
                    'predicted_sales' => $predictedSales,
                    'lower_bound' => round($predictedSales * 0.85, 2),
                    'upper_bound' => round($predictedSales * 1.15, 2),
                    'actual_sales' => null,
                    'prediction_error' => null,
                    'confidence_score' => 80.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $revenueRecords[] = [
                    'forecast_run_id' => $runs->get('revenue')->id,
                    'forecast_date' => $forecastDate,
                    'predicted_sales' => $predictedRevenue,
                    'lower_bound' => round($predictedRevenue * 0.85, 2),
                    'upper_bound' => round($predictedRevenue * 1.15, 2),
                    'actual_sales' => null,
                    'prediction_error' => null,
                    'confidence_score' => 80.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (
                count($salesRecords) !== 30 ||
                count($revenueRecords) !== 30
            ) {
                throw new RuntimeException(
                    'Expected exactly 30 daily records per forecast run.'
                );
            }

            DB::table('sales_forecasts')->insert($salesRecords);
            DB::table('sales_forecasts')->insert($revenueRecords);

            // Mark each run completed only after its forecast output exists.
            foreach (['sales', 'revenue'] as $type) {
                $run = $runs->get($type);

                $recordCount = DB::table('sales_forecasts')
                    ->where('forecast_run_id', $run->id)
                    ->count();

                if ($recordCount !== 30) {
                    throw new RuntimeException(
                        "The {$type} forecast output is incomplete."
                    );
                }

                $summary = [
                    'records_generated' => $recordCount,
                    'forecast_period' => '2026-11-01 to 2026-11-30',
                    'historical_period' => '2025-11-01 to 2026-10-31',
                    'data_label' => 'synthetic_simulated_forecast',
                    'actual_values_available' => false,
                ];

                if ($type === 'sales') {
                    $summary['method'] =
                        'Weekday average using all calendar days, including zero-sales days';
                } else {
                    $summary['method'] =
                        'Weighted daily revenue average of the latest three historical months';

                    $summary['monthly_weights'] = [
                        '2026-10' => 0.50,
                        '2026-09' => 0.30,
                        '2026-08' => 0.20,
                    ];
                }

                DB::table('forecast_runs')
                    ->where('id', $run->id)
                    ->update([
                        'status' => 'completed',
                        'results_summary' => json_encode($summary),
                        'started_at' => $now,
                        'completed_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            echo "Historical calendar days: 365" . PHP_EOL;
            echo "Historical completed sales days: "
                . DB::table('orders')
                    ->where('status', 'completed')
                    ->whereBetween('ordered_at', [
                        '2025-11-01 00:00:00',
                        '2026-10-31 23:59:59',
                    ])
                    ->distinct()
                    ->count(DB::raw('DATE(ordered_at)'))
                . PHP_EOL;

            echo "Sales forecast records: " . count($salesRecords) . PHP_EOL;
            echo "Revenue forecast records: " . count($revenueRecords) . PHP_EOL;
            echo "Total forecast records: "
                . (count($salesRecords) + count($revenueRecords))
                . PHP_EOL;

            echo "Weighted daily revenue baseline: "
                . number_format($weightedDailyRevenue, 2)
                . PHP_EOL;

            echo "Confidence score: 80.00%" . PHP_EOL;
            echo "Actual sales and prediction errors: NULL" . PHP_EOL;
            echo "Sales and revenue forecast runs: completed" . PHP_EOL;
        });
    }
}