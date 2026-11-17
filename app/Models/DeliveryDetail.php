<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'delivery_staff_id',
    'recipient_name',
    'recipient_phone',
    'delivery_address',
    'delivery_instructions',
    'delivery_fee',
    'status',
    'assigned_at',
    'picked_up_at',
    'delivered_at',
    'failure_reason',
])]
class DeliveryDetail extends Model
{
    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_staff_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class);
    }
}