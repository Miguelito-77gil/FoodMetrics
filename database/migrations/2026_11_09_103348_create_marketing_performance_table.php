<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_performance', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('marketing_campaigns')
                ->cascadeOnDelete();

            $table->date('performance_date');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('leads')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);

            $table->decimal('revenue_generated', 12, 2)->default(0);
            $table->decimal('amount_spent', 12, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'performance_date']);

            $table->index('performance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_performance');
    }
};
