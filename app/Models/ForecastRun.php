<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'forecast_name',
    'forecast_type',
    'model_name',
    'model_version',
    'training_start_date',
    'training_end_date',
    'forecast_start_date',
    'forecast_end_date',
    'status',
    'parameters',
    'results_summary',
    'error_message',
    'created_by',
    'started_at',
    'completed_at',
])]
class ForecastRun extends Model
{
    protected function casts(): array
    {
        return [
            'training_start_date' => 'date',
            'training_end_date' => 'date',
            'forecast_start_date' => 'date',
            'forecast_end_date' => 'date',
            'parameters' => 'array',
            'results_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesForecasts(): HasMany
    {
        return $this->hasMany(SalesForecast::class);
    }

    public function inventoryRecommendations(): HasMany
    {
        return $this->hasMany(InventoryRecommendation::class);
    }
}