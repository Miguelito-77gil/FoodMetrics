
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->text('instructions')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id']);
            $table->index(['is_active', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
