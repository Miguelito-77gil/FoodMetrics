<?php

namespace App\Providers;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Ingredient;
use App\Policies\DeliveryDetailPolicy;
use App\Policies\OrderPolicy;
use App\Policies\InventoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(DeliveryDetail::class, DeliveryDetailPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Ingredient::class, InventoryPolicy::class);
    }
}