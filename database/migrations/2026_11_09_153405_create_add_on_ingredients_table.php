<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_on_ingredients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('add_on_id')
                ->constrained('add_ons')
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients')
                ->restrictOnDelete();

            $table->decimal('quantity_required', 12, 3);
            $table->string('unit', 20);
            $table->text('preparation_notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['add_on_id', 'ingredient_id'],
                'add_on_ingredients_addon_ingredient_unique'
            );

            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('add_on_ingredients');
    }
};