<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }

    public function manageStatus(User $user, Ingredient $ingredient): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }
    public function manageTransactions(User $user): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }
}