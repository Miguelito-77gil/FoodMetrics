<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'price',
    'image',
    'is_available',
    'sort_order',
])]
class AddOn extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_add_ons')
            ->withTimestamps();
    }

    public function addOnIngredients(): HasMany
    {
        return $this->hasMany(AddOnIngredient::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'add_on_ingredients')
            ->withPivot([
                'quantity_required',
                'unit',
                'preparation_notes',
            ])
            ->withTimestamps();
    }
}