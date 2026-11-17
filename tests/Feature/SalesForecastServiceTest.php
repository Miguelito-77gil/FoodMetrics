<?php

namespace Tests\Feature;

use App\Models\ForecastRun;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SalesForecast;
use App\Models\User;
use App\Services\SalesForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeCompletedOrder(
        User $user,
        string $date,
        float $amount
    ): Order {
        return Order::create([
            'order_number' => 'FC-' . strtoupper(Str::random(12)),
            'user_id' => $user->id,
            'order_type' => 'takeout',
            'status' => 'completed',
            'subtotal' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => $amount,
            'ordered_at' => $date . ' 12:00:00',
        ]);
    }

    private function makeCompletedPayment(
        Order $order,
        User $user,
        string $date,
        float $amount
    ): Payment {
        return Payment::create([
            'payment_number' => 'FC-' . Str::uuid(),
            'order_id' => $order->id,
            'received_by' => $user->id,
            'payment_method' => 'cash',
            'amount' => number_format($amount, 2, '.', ''),
            'status' => 'completed',
            'paid_at' => $date . ' 12:00:00',
        ]);
    }

    public function test_sales_forecast_uses_completed_orders_and_weekday_average(): void
    {
        $user = $this->makeUser();

        // One completed order for each weekday in the seven-day training window.
        for ($day = 9; $day <= 15; $day++) {
            $date = sprintf('2026-11-%02d', $day);
            $amount = ($day - 8) * 100;

            $this->makeCompletedOrder($user, $date, $amount);
        }

        // A cancelled order must not affect the forecast.
        Order::create([
            'order_number' => 'FC-CANCELLED',
            'user_id' => $user->id,
            'order_type' => 'takeout',
            'status' => 'cancelled',
            'subtotal' => 9000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 9000,
            'ordered_at' => '2026-11-09 13:00:00',
        ]);

        $run = app(SalesForecastService::class)->generate(
            'sales',
            '2026-11-16',
            '2026-11-16',
            $user->id,
            7
        );

        $forecast = $run->salesForecasts->first();

        $this->assertNotNull($forecast);
        $this->assertSame('2026-11-16', $forecast->forecast_date->toDateString());
        $this->assertEquals(100.00, (float) $forecast->predicted_sales);
        $this->assertNull($forecast->confidence_score);
        $this->assertNull($forecast->lower_bound);
        $this->assertNull($forecast->upper_bound);
        $this->assertSame('completed', $run->status);
        $this->assertSame(
            'synthetic_simulated_historical_data',
            $run->results_summary['data_label']
        );
    }

    public function test_revenue_forecast_uses_completed_payments(): void
    {
        $user = $this->makeUser();

        for ($day = 9; $day <= 15; $day++) {
            $date = sprintf('2026-11-%02d', $day);
            $order = $this->makeCompletedOrder($user, $date, 1000);

            $this->makeCompletedPayment(
                $order,
                $user,
                $date,
                ($day - 8) * 200
            );
        }

        // Failed and refunded payments must not affect revenue.
        $extraOrder = $this->makeCompletedOrder(
            $user,
            '2026-11-09',
            1000
        );

        Payment::create([
            'payment_number' => 'FC-FAILED',
            'order_id' => $extraOrder->id,
            'received_by' => $user->id,
            'payment_method' => 'cash',
            'amount' => '5000.00',
            'status' => 'failed',
            'paid_at' => '2026-11-09 14:00:00',
        ]);

        $run = app(SalesForecastService::class)->generate(
            'revenue',
            '2026-11-16',
            '2026-11-16',
            $user->id,
            7
        );

        $forecast = $run->salesForecasts->first();

        $this->assertNotNull($forecast);
        $this->assertEquals(200.00, (float) $forecast->predicted_sales);
        $this->assertSame('revenue', $run->forecast_type);
        $this->assertSame('completed', $run->status);
    }

    public function test_forecast_creates_a_new_run_without_overwriting_existing_records(): void
    {
        $user = $this->makeUser();

        $existingRun = ForecastRun::create([
            'forecast_name' => 'Existing Forecast',
            'forecast_type' => 'sales',
            'model_name' => 'Existing Model',
            'forecast_start_date' => '2026-11-16',
            'forecast_end_date' => '2026-11-16',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $existingForecast = SalesForecast::create([
            'forecast_run_id' => $existingRun->id,
            'forecast_date' => '2026-11-16',
            'predicted_sales' => 999.00,
        ]);

        $newRun = app(SalesForecastService::class)->generate(
            'sales',
            '2026-11-16',
            '2026-11-16',
            $user->id,
            7
        );

        $this->assertNotSame($existingRun->id, $newRun->id);
        $this->assertSame(2, ForecastRun::count());
        $this->assertSame(2, SalesForecast::count());

        $this->assertEquals(
            999.00,
            (float) $existingForecast->fresh()->predicted_sales
        );
    }

    public function test_invalid_forecast_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(SalesForecastService::class)->generate(
            'inventory',
            '2026-11-16',
            '2026-11-20'
        );
    }

    public function test_forecast_start_date_must_be_in_the_future(): void
    {
        $this->expectException(ValidationException::class);

        app(SalesForecastService::class)->generate(
            'sales',
            '2026-11-15',
            '2026-11-20'
        );
    }
}