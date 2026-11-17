<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('promotion_name', 150);
            $table->string('promotion_code', 50)->unique();

            $table->enum('discount_type', [
                'percentage',
                'fixed_amount',
            ]);

            $table->decimal('discount_value', 12, 2);

            $table->decimal('minimum_order_amount', 12, 2)->default(0);
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            $table->enum('status', [
                'draft',
                'scheduled',
                'active',
                'expired',
                'cancelled',
            ])->default('draft');

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index(['discount_type', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
