<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingredient_id',
    'forecast_run_id',
    'current_stock',
    'recommended_quantity',
    'estimated_cost',
    'recommended_order_date',
    'expected_stockout_date',
    'priority',
    'status',
    'reason',
    'reviewed_by',
    'reviewed_at',
])]
class InventoryRecommendation extends Model
{
    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'recommended_quantity' => 'decimal:3',
            'estimated_cost' => 'decimal:2',
            'recommended_order_date' => 'date',
            'expected_stockout_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}