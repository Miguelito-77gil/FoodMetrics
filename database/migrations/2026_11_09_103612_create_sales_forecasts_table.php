<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_forecasts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forecast_run_id')
                ->constrained('forecast_runs')
                ->cascadeOnDelete();

            $table->date('forecast_date');

            $table->decimal('predicted_sales', 12, 2)->default(0);
            $table->decimal('lower_bound', 12, 2)->nullable();
            $table->decimal('upper_bound', 12, 2)->nullable();

            $table->decimal('actual_sales', 12, 2)->nullable();
            $table->decimal('prediction_error', 12, 2)->nullable();

            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['forecast_run_id', 'forecast_date']);
            $table->index('forecast_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_forecasts');
    }
};
