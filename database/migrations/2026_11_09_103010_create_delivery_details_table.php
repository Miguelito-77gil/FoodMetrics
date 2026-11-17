<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('delivery_staff_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 30);
            $table->text('delivery_address');
            $table->text('delivery_instructions')->nullable();

            $table->decimal('delivery_fee', 12, 2)->default(0);

            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'delivered',
                'failed',
                'cancelled',
            ])->default('pending');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('delivery_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_details');
    }
};
