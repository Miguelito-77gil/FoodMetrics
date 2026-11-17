<?php

namespace Database\Seeders;

use App\Models\MarketingCampaign;
use App\Models\MarketingPerformance;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketingPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $distribution = [
            'MKT-2025-001' => 12,
            'MKT-2025-002' => 14,
            'MKT-2026-001' => 12,
            'MKT-2026-002' => 12,
            'MKT-2026-003' => 14,
            'MKT-2026-004' => 12,
            'MKT-2026-005' => 10,
            'MKT-2026-006' => 12,
            'MKT-2026-007' => 10,
            'MKT-2026-008' => 12,
        ];

        DB::transaction(function () use ($distribution) {
            $campaigns = MarketingCampaign::query()
                ->orderBy('campaign_code')
                ->get()
                ->keyBy('campaign_code');

            if ($campaigns->count() !== 10) {
                throw new RuntimeException(
                    'Expected exactly 10 marketing campaigns.'
                );
            }

            if (MarketingPerformance::query()->exists()) {
                throw new RuntimeException(
                    'Marketing performance records already exist. '
                    . 'Seeder stopped to protect existing data.'
                );
            }

            if (array_diff_key($distribution, $campaigns->all())) {
                throw new RuntimeException(
                    'One or more expected campaign codes are missing.'
                );
            }

            $totalCreated = 0;
            $totalSpendCents = 0;

            foreach ($distribution as $campaignCode => $recordCount) {
                $campaign = $campaigns->get($campaignCode);

                if (!$campaign->end_date) {
                    throw new RuntimeException(
                        "Campaign {$campaignCode} has no end date."
                    );
                }

                $start = Carbon::parse($campaign->start_date)->startOfDay();
                $end = Carbon::parse($campaign->end_date)->startOfDay();

                if ($start->gt($end)) {
                    throw new RuntimeException(
                        "Invalid date range for {$campaignCode}."
                    );
                }

                $availableDays = $start->diffInDays($end) + 1;

                if ($recordCount > $availableDays) {
                    throw new RuntimeException(
                        "Not enough unique campaign dates for {$campaignCode}."
                    );
                }

                // Distribute actual campaign spending exactly in cents.
                $actualCostCents = (int) round(
                    (float) $campaign->actual_cost * 100
                );

                $baseSpend = intdiv($actualCostCents, $recordCount);
                $remainder = $actualCostCents % $recordCount;

                for ($i = 0; $i < $recordCount; $i++) {
                    // Spread observations across the campaign period.
                    $dayOffset = $recordCount === 1
                        ? 0
                        : (int) floor(
                            $i * ($availableDays - 1) / ($recordCount - 1)
                        );

                    $performanceDate = $start->copy()
                        ->addDays($dayOffset);

                    // Synthetic marketing activity.
                    $impressions = random_int(800, 8500);
                    $reach = random_int(
                        max(1, (int) ceil($impressions * 0.55)),
                        $impressions
                    );

                    $clicks = random_int(
                        max(1, (int) floor($reach * 0.02)),
                        max(1, (int) floor($reach * 0.12))
                    );

                    $leads = random_int(
                        0,
                        max(0, (int) floor($clicks * 0.45))
                    );

                    $conversions = random_int(
                        0,
                        $leads
                    );

                    $spendCents = $baseSpend
                        + ($i < $remainder ? 1 : 0);

                    $amountSpent = $spendCents / 100;

                    // Simulated revenue attribution.
                    $averageOrderValue = random_int(350, 950);

                    $revenueGenerated = round(
                        $conversions * $averageOrderValue,
                        2
                    );

                    MarketingPerformance::create([
                        'campaign_id' => $campaign->id,
                        'performance_date' => $performanceDate->toDateString(),
                        'impressions' => $impressions,
                        'reach' => $reach,
                        'clicks' => $clicks,
                        'leads' => $leads,
                        'conversions' => $conversions,
                        'revenue_generated' => $revenueGenerated,
                        'amount_spent' => $amountSpent,
                        'notes' => 'Synthetic simulated historical marketing performance data.',
                    ]);

                    $totalCreated++;
                    $totalSpendCents += $spendCents;
                }

                echo "{$campaignCode}: {$recordCount} records created"
                    . " | Spending: "
                    . number_format($actualCostCents / 100, 2)
                    . PHP_EOL;
            }

            if ($totalCreated !== 120) {
                throw new RuntimeException(
                    "Expected 120 records, created {$totalCreated}."
                );
            }

            if ($totalSpendCents !== (int) round(
                (float) $campaigns->sum('actual_cost') * 100
            )) {
                throw new RuntimeException(
                    'Generated spending does not match campaign actual costs.'
                );
            }

            echo PHP_EOL;
            echo "Total campaigns processed: " . $campaigns->count() . PHP_EOL;
            echo "Total performance records created: {$totalCreated}" . PHP_EOL;
            echo "Total campaign spending allocated: "
                . number_format($totalSpendCents / 100, 2) . PHP_EOL;
        });
    }
}