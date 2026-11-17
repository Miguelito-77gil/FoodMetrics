<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest')
    ->name('login.submit');

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', function () {
        return match (request()->user()->role) {
            'admin' => redirect()->route('admin.index'),
            'cashier' => redirect()->route('orders.index'),
            'delivery_staff' => redirect()->route('delivery.index'),
            'inventory_staff' => redirect()->route('inventory.index'),
            default => abort(403, 'Unauthorized dashboard access.'),
        };
    })->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])
                ->name('index');

            Route::post('/forecasts', [
                AdminDashboardController::class,
                'generateForecast',
            ])->name('forecasts.generate');

            Route::post('/inventory-recommendations/{recommendation}/approve', [
                AdminDashboardController::class,
                'approveInventoryRecommendation',
            ])->whereNumber('recommendation')->name('inventory-recommendations.approve');

            Route::post('/inventory-recommendations/{recommendation}/dismiss', [
                AdminDashboardController::class,
                'dismissInventoryRecommendation',
            ])->whereNumber('recommendation')->name('inventory-recommendations.dismiss');

            Route::get('/deliveries', [
                DeliveryController::class,
                'assignmentIndex',
            ])->name('deliveries.index');

            Route::post('/deliveries/{delivery}/assign', [
                DeliveryController::class,
                'assign',
            ])->whereNumber('delivery')->name('deliveries.assign');
        });


    Route::middleware('role:admin,cashier')->group(function () {
        Route::get('/orders', [
            OrderController::class,
            'index',
        ])->name('orders.index');

        Route::get('/orders/{order}', [
            OrderController::class,
            'show',
        ])->whereNumber('order')->name('orders.show');
    });
    Route::prefix('cashier')
        ->name('cashier.')
        ->middleware('role:admin,cashier')
        ->group(function () {
            Route::get('/', function () {
                return redirect()->route('orders.index');
            })->name('index');

            Route::get('/orders/create', [
                OrderController::class,
                'create',
            ])->name('orders.create');

            Route::post('/orders', [
                OrderController::class,
                'store',
            ])->name('orders.store');

            Route::patch('/orders/{order}/status', [
                OrderController::class,
                'updateStatus',
            ])->whereNumber('order')->name('orders.status.update');

            Route::post('/orders/{order}/payments', [
                PaymentController::class,
                'store',
            ])->whereNumber('order')->name('orders.payments.store');
            Route::post('/orders/{order}/complete', [
                OrderController::class,
                'complete',
            ])->whereNumber('order')->name('orders.complete');
        });

    Route::prefix('inventory')
        ->name('inventory.')
        ->middleware('role:admin,inventory_staff')
        ->group(function () {
            Route::get('/', [InventoryController::class, 'index'])
                ->name('index');

            Route::get('/ingredients/create', [InventoryController::class, 'create'])
                ->name('ingredients.create');

            Route::post('/ingredients', [InventoryController::class, 'store'])
                ->name('ingredients.store');

            Route::get('/ingredients/{ingredient}/edit', [InventoryController::class, 'edit'])
                ->whereNumber('ingredient')
                ->name('ingredients.edit');

            Route::put('/ingredients/{ingredient}', [InventoryController::class, 'update'])
                ->whereNumber('ingredient')
                ->name('ingredients.update');

            Route::patch('/ingredients/{ingredient}/status', [InventoryController::class, 'toggleStatus'])
                ->whereNumber('ingredient')
                ->name('ingredients.status');
            Route::get('/stock-in', [InventoryController::class, 'createStockIn'])
                ->name('transactions.stock-in.create');

            Route::post('/stock-in', [InventoryController::class, 'storeStockIn'])
                ->name('transactions.stock-in.store');

            Route::get('/adjustments/create', [InventoryController::class, 'createAdjustment'])
                ->name('transactions.adjustment.create');

            Route::post('/adjustments', [InventoryController::class, 'storeAdjustment'])
                ->name('transactions.adjustment.store');

            Route::get('/waste/create', [InventoryController::class, 'createWaste'])
                ->name('transactions.waste.create');

            Route::post('/waste', [InventoryController::class, 'storeWaste'])
                ->name('transactions.waste.store');

            Route::get('/movements', [InventoryController::class, 'movements'])
                ->name('movements.index');
        });

    Route::prefix('delivery')
        ->name('delivery.')
        ->middleware('role:admin,delivery_staff')
        ->group(function () {
            Route::get('/', [DeliveryController::class, 'index'])
                ->name('index');

            Route::patch('/{delivery}/status', [
                DeliveryController::class,
                'updateStatus',
            ])->whereNumber('delivery')->name('status.update');

            Route::get('/{delivery}', [DeliveryController::class, 'show'])
                ->whereNumber('delivery')
                ->name('show');
        });

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
});