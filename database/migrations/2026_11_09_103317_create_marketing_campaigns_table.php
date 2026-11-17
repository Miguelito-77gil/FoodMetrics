<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('campaign_name', 150);
            $table->string('campaign_code', 50)->unique();

            $table->enum('campaign_type', [
                'social_media',
                'email',
                'sms',
                'print',
                'event',
                'other',
            ]);

            $table->string('platform', 100)->nullable();

            $table->text('description')->nullable();

            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->default(0);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->enum('status', [
                'draft',
                'scheduled',
                'active',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->text('target_audience')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index(['campaign_type', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
