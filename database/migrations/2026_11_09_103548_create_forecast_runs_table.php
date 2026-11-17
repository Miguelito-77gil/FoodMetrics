<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();

            $table->string('forecast_name', 150);

            $table->enum('forecast_type', [
                'sales',
                'demand',
                'inventory',
                'revenue',
            ]);

            $table->string('model_name', 100);
            $table->string('model_version', 50)->nullable();

            $table->date('training_start_date')->nullable();
            $table->date('training_end_date')->nullable();

            $table->date('forecast_start_date');
            $table->date('forecast_end_date');

            $table->enum('status', [
                'pending',
                'running',
                'completed',
                'failed',
            ])->default('pending');

            $table->text('parameters')->nullable();
            $table->text('results_summary')->nullable();
            $table->text('error_message')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['forecast_type', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
