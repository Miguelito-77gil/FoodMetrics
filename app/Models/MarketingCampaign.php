<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'campaign_name',
    'campaign_code',
    'campaign_type',
    'platform',
    'description',
    'budget',
    'actual_cost',
    'start_date',
    'end_date',
    'status',
    'target_audience',
    'created_by',
])]
class MarketingCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function performances(): HasMany
    {
        return $this->hasMany(MarketingPerformance::class, 'campaign_id');
    }
}