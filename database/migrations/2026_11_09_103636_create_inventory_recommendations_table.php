<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_recommendations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients')
                ->restrictOnDelete();

            $table->foreignId('forecast_run_id')
                ->nullable()
                ->constrained('forecast_runs')
                ->nullOnDelete();

            $table->decimal('current_stock', 12, 3);
            $table->decimal('recommended_quantity', 12, 3);
            $table->decimal('estimated_cost', 12, 2)->default(0);

            $table->date('recommended_order_date')->nullable();
            $table->date('expected_stockout_date')->nullable();

            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'critical',
            ])->default('medium');

            $table->enum('status', [
                'pending',
                'approved',
                'ordered',
                'dismissed',
            ])->default('pending');

            $table->text('reason')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['ingredient_id', 'status']);
            $table->index('recommended_order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_recommendations');
    }
};
