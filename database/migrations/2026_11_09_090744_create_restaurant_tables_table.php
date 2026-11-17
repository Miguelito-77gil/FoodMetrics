
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();

            $table->string('table_number', 30)->unique();
            $table->unsignedTinyInteger('capacity');
            $table->string('location', 100)->nullable();

            $table->enum('status', [
                'available',
                'occupied',
                'reserved',
                'maintenance',
            ])->default('available');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
