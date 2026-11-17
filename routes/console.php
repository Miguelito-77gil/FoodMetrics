<?php

use App\Models\InventoryRecommendation;
use App\Models\User;
use App\Services\InventoryRecommendationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $hasPendingInventoryRecommendations = InventoryRecommendation::query()
        ->where('status', 'pending')
        ->exists();

    if ($hasPendingInventoryRecommendations) {
        Log::info(
            'Daily inventory forecast skipped: pending inventory recommendations still require review.'
        );

        return;
    }

    $adminId = User::query()
        ->where('role', 'admin')
        ->where('is_active', true)
        ->orderBy('id')
        ->value('id');

    if (! $adminId) {
        Log::warning(
            'Daily inventory forecast skipped: no active admin account exists.'
        );

        return;
    }

    $forecastStartDate = now()->addDay()->toDateString();
    $forecastEndDate = now()->addDays(30)->toDateString();

    $run = app(InventoryRecommendationService::class)->generate(
        $forecastStartDate,
        $forecastEndDate,
        (int) $adminId,
        90
    );

    Log::info('Daily inventory forecast generated successfully.', [
        'forecast_run_id' => $run->id,
        'forecast_start_date' => $forecastStartDate,
        'forecast_end_date' => $forecastEndDate,
        'created_by' => $adminId,
    ]);
})
    ->name('daily-inventory-forecast')
    ->timezone('Asia/Manila')
    ->dailyAt('10:00')
    ->withoutOverlapping();