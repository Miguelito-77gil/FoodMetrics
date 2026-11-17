<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delivery_detail_id',
    'updated_by',
    'previous_status',
    'new_status',
    'remarks',
    'changed_at',
])]
class DeliveryStatusHistory extends Model
{
    protected $table = 'delivery_status_history';

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function deliveryDetail(): BelongsTo
    {
        return $this->belongsTo(DeliveryDetail::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}