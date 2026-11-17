<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->string('payment_number', 50)->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('payment_method', [
                'cash',
                'gcash',
                'bank_transfer',
                'card',
                'other',
            ]);

            $table->decimal('amount', 12, 2);

            $table->string('transaction_reference', 120)->nullable();

            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'refunded',
            ])->default('completed');

            $table->timestamp('paid_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['payment_method', 'paid_at']);
            $table->index('received_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
