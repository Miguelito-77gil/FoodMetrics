<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id',
    'performance_date',
    'impressions',
    'reach',
    'clicks',
    'leads',
    'conversions',
    'revenue_generated',
    'amount_spent',
    'notes',
])]
class MarketingPerformance extends Model
{
    protected $table = 'marketing_performance';

    protected function casts(): array
    {
        return [
            'performance_date' => 'date',
            'impressions' => 'integer',
            'reach' => 'integer',
            'clicks' => 'integer',
            'leads' => 'integer',
            'conversions' => 'integer',
            'revenue_generated' => 'decimal:2',
            'amount_spent' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}