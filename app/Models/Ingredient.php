<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'base_unit',
    'current_stock',
    'minimum_stock',
    'cost_per_base_unit',
    'storage_location',
    'notes',
    'is_active',
])]
class Ingredient extends Model
{
    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'cost_per_base_unit' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredients')
            ->withPivot([
                'quantity_required',
                'unit',
                'preparation_notes',
            ])
            ->withTimestamps();
    }

    public function addOnIngredients(): HasMany
    {
        return $this->hasMany(AddOnIngredient::class);
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'add_on_ingredients')
            ->withPivot([
                'quantity_required',
                'unit',
                'preparation_notes',
            ])
            ->withTimestamps();
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function inventoryRecommendations(): HasMany
    {
        return $this->hasMany(InventoryRecommendation::class);
    }
}