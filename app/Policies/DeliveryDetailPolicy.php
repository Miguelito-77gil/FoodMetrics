<?php

namespace App\Policies;

use App\Models\DeliveryDetail;
use App\Models\User;

class DeliveryDetailPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'delivery_staff'], true);
    }

    public function view(User $user, DeliveryDetail $delivery): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'delivery_staff'
            && (int) $delivery->delivery_staff_id === (int) $user->id;
    }

    public function update(User $user, DeliveryDetail $delivery): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'delivery_staff'
            && (int) $delivery->delivery_staff_id === (int) $user->id;
    }

    public function assign(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, DeliveryDetail $delivery): bool
    {
        return $user->role === 'admin';
    }
}