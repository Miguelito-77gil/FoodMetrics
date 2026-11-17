<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('remarks')->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            $table->index(['order_id', 'changed_at']);
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};