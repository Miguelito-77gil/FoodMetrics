<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function updateStatus(User $user, Order $order): bool
    {
        return in_array($user->role, ['admin', 'cashier'], true);
    }

    public function complete(User $user, Order $order): bool
    {
        return in_array($user->role, ['admin', 'cashier'], true)
            && in_array($order->order_type, ['dine_in', 'takeout'], true);
    }
}