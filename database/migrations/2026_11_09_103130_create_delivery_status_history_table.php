<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_detail_id')
                ->constrained('delivery_details')
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

            $table->index(['delivery_detail_id', 'changed_at']);
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_history');
    }
};
