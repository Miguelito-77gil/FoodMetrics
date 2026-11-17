<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id',
    'forecast_date',
    'predicted_sales',
    'lower_bound',
    'upper_bound',
    'actual_sales',
    'prediction_error',
    'confidence_score',
])]
class SalesForecast extends Model
{
    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'predicted_sales' => 'decimal:2',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'actual_sales' => 'decimal:2',
            'prediction_error' => 'decimal:2',
            'confidence_score' => 'decimal:2',
        ];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}