<?php

namespace Tests\Feature;

use App\Models\ForecastRun;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminForecastControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeCashier(): User
    {
        return User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    private function makeCompletedOrder(
        User $cashier,
        string $date,
        float $amount
    ): Order {
        return Order::create([
            'order_number' => 'FC-' . strtoupper(Str::random(12)),
            'user_id' => $cashier->id,
            'order_type' => 'takeout',
            'status' => 'completed',
            'subtotal' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => $amount,
            'notes' => 'Forecast controller test.',
            'ordered_at' => $date . ' 12:00:00',
        ]);
    }

    public function test_admin_can_generate_a_sales_forecast(): void
    {
        $admin = $this->makeAdmin();
        $cashier = $this->makeCashier();

        $this->makeCompletedOrder($cashier, '2026-11-09', 100);

        $response = $this->actingAs($admin)->post(
            route('admin.forecasts.generate'),
            [
                'forecast_type' => 'sales',
                'forecast_start_date' => '2026-11-16',
                'forecast_end_date' => '2026-11-18',
                'lookback_days' => 7,
            ]
        );

        $response->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('forecast_runs', [
            'forecast_type' => 'sales',
            'model_name' => 'Weekday Average Baseline',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);

        $run = ForecastRun::query()
            ->where('forecast_type', 'sales')
            ->latest('id')
            ->firstOrFail();

        $this->assertCount(3, $run->salesForecasts);

        $forecastExists = \Illuminate\Support\Facades\DB::table('sales_forecasts')
            ->where('forecast_run_id', $run->id)
            ->whereDate('forecast_date', '2026-11-16')
            ->where('predicted_sales', 100)
            ->exists();

        $this->assertTrue(
            $forecastExists,
            'Expected November 16 sales forecast of 100.00 was not found.'
        );
    }

    public function test_cashier_cannot_generate_a_forecast(): void
    {
        $cashier = $this->makeCashier();

        $response = $this->actingAs($cashier)->post(
            route('admin.forecasts.generate'),
            [
                'forecast_type' => 'sales',
                'forecast_start_date' => '2026-11-16',
                'forecast_end_date' => '2026-11-18',
                'lookback_days' => 7,
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('forecast_runs', 0);
    }

    public function test_invalid_forecast_input_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(
            route('admin.forecasts.generate'),
            [
                'forecast_type' => 'inventory',
                'forecast_start_date' => '2026-11-16',
                'forecast_end_date' => '2026-11-18',
                'lookback_days' => 5,
            ]
        );

        $response->assertSessionHasErrors([
            'forecast_type',
            'lookback_days',
        ]);

        $this->assertDatabaseCount('forecast_runs', 0);
    }

    public function test_admin_dashboard_receives_latest_forecast_runs(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.index'));

        $response->assertOk()
            ->assertViewHas('latestForecastRuns');
    }
}