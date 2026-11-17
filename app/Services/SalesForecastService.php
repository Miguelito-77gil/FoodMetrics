<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesForecastService
{
    /**
     * Generate a new daily sales or revenue forecast.
     *
     * Sales uses completed orders grouped by ordered_at.
     * Revenue uses completed payments grouped by paid_at.
     *
     * Existing forecast runs and records are never overwritten.
     */
    public function generate(
        string $forecastType,
        string $forecastStartDate,
        string $forecastEndDate,
        ?int $createdBy = null,
        int $lookbackDays = 365
    ): ForecastRun {
        $forecastType = strtolower(trim($forecastType));

        if (!in_array($forecastType, ['sales', 'revenue'], true)) {
            throw ValidationException::withMessages([
                'forecast_type' => 'Forecast type must be sales or revenue.',
            ]);
        }

        if ($lookbackDays < 7 || $lookbackDays > 730) {
            throw ValidationException::withMessages([
                'lookback_days' => 'The historical lookback must be between 7 and 730 days.',
            ]);
        }

        $forecastStart = CarbonImmutable::parse($forecastStartDate)
            ->startOfDay();

        $forecastEnd = CarbonImmutable::parse($forecastEndDate)
            ->startOfDay();

        if ($forecastStart->lessThanOrEqualTo(
            CarbonImmutable::today()
        )) {
            throw ValidationException::withMessages([
                'forecast_start_date' => 'The forecast must start on a future date.',
            ]);
        }

        if ($forecastEnd->lessThan($forecastStart)) {
            throw ValidationException::withMessages([
                'forecast_end_date' => 'The forecast end date must not be before the start date.',
            ]);
        }

        $trainingStart = $forecastStart->subDays($lookbackDays);
        $trainingEnd = $forecastStart->subDay();

        return DB::transaction(function () use (
            $forecastType,
            $forecastStart,
            $forecastEnd,
            $trainingStart,
            $trainingEnd,
            $lookbackDays,
            $createdBy
        ) {
            $dailyTotals = $this->getHistoricalDailyTotals(
                $forecastType,
                $trainingStart,
                $trainingEnd
            );

            $weekdayTotals = array_fill(0, 7, 0.0);
            $weekdayCounts = array_fill(0, 7, 0);

            for (
                $date = $trainingStart;
                $date->lessThanOrEqualTo($trainingEnd);
                $date = $date->addDay()
            ) {
                $weekday = $date->dayOfWeek;
                $dateKey = $date->toDateString();

                $weekdayTotals[$weekday] +=
                    $dailyTotals[$dateKey] ?? 0.0;

                $weekdayCounts[$weekday]++;
            }

            $weekdayAverages = [];

            for ($weekday = 0; $weekday < 7; $weekday++) {
                $weekdayAverages[$weekday] =
                    $weekdayCounts[$weekday] > 0
                        ? $weekdayTotals[$weekday] / $weekdayCounts[$weekday]
                        : 0.0;
            }

            $forecastName = ucfirst($forecastType)
                . ' Forecast ('
                . $forecastStart->toDateString()
                . ' to '
                . $forecastEnd->toDateString()
                . ')';

            $run = ForecastRun::create([
                'forecast_name' => $forecastName,
                'forecast_type' => $forecastType,
                'model_name' => 'Weekday Average Baseline',
                'model_version' => '1.0',
                'training_start_date' => $trainingStart->toDateString(),
                'training_end_date' => $trainingEnd->toDateString(),
                'forecast_start_date' => $forecastStart->toDateString(),
                'forecast_end_date' => $forecastEnd->toDateString(),
                'status' => 'running',
                'parameters' => [
                    'method' => 'weekday_average',
                    'lookback_days' => $lookbackDays,
                    'source' => $forecastType === 'sales'
                        ? 'completed_orders'
                        : 'completed_payments',
                    'aggregation' => 'daily_total',
                    'zero_sales_days_included' => true,
                ],
                'results_summary' => null,
                'created_by' => $createdBy,
                'started_at' => now(),
            ]);

            $forecastRows = [];

            for (
                $date = $forecastStart;
                $date->lessThanOrEqualTo($forecastEnd);
                $date = $date->addDay()
            ) {
                $predictedAmount = round(
                    $weekdayAverages[$date->dayOfWeek],
                    2
                );

                $forecastRows[] = [
                    'forecast_date' => $date->toDateString(),
                    'predicted_sales' => number_format(
                        $predictedAmount,
                        2,
                        '.',
                        ''
                    ),
                    'lower_bound' => null,
                    'upper_bound' => null,
                    'actual_sales' => null,
                    'prediction_error' => null,
                    'confidence_score' => null,
                ];
            }

            $run->salesForecasts()->createMany($forecastRows);

            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
                'results_summary' => [
                    'method' => 'weekday_average',
                    'generated_records' => count($forecastRows),
                    'forecast_type' => $forecastType,
                    'data_label' => 'synthetic_simulated_historical_data',
                    'actual_values_available' => false,
                    'confidence_score_calculated' => false,
                    'prediction_bounds_calculated' => false,
                ],
            ]);

            return $run->fresh('salesForecasts');
        }, 3);
    }

    /**
     * Retrieve historical daily totals for the selected forecast type.
     *
     * Missing dates are treated as zero during weekday averaging.
     */
    private function getHistoricalDailyTotals(
        string $forecastType,
        CarbonImmutable $trainingStart,
        CarbonImmutable $trainingEnd
    ): array {
        if ($forecastType === 'sales') {
            $records = Order::query()
                ->where('status', 'completed')
                ->whereNotNull('ordered_at')
                ->whereBetween('ordered_at', [
                    $trainingStart->startOfDay(),
                    $trainingEnd->endOfDay(),
                ])
                ->selectRaw(
                    'DATE(ordered_at) as record_date, SUM(total_amount) as daily_total'
                )
                ->groupByRaw('DATE(ordered_at)')
                ->get();
        } else {
            $records = Payment::query()
                ->where('status', 'completed')
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [
                    $trainingStart->startOfDay(),
                    $trainingEnd->endOfDay(),
                ])
                ->selectRaw(
                    'DATE(paid_at) as record_date, SUM(amount) as daily_total'
                )
                ->groupByRaw('DATE(paid_at)')
                ->get();
        }

        $dailyTotals = [];

        foreach ($records as $record) {
            $dailyTotals[$record->record_date] =
                (float) $record->daily_total;
        }

        return $dailyTotals;
    }
}