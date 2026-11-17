@extends('layouts.app')

@section('title', 'Inventory')
@section('breadcrumb', 'Inventory')

@section('content')
<div class="fm-page space-y-6">

    <div class="fm-page-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="fm-page-title">Inventory Management</h1>
            <p class="fm-page-description">
                Monitor ingredients, stock levels, and inventory records.
            </p>
        </div>

        <a href="{{ route('inventory.ingredients.create') }}"
           class="fm-btn fm-btn-primary">
            Add Ingredient
        </a>
    </div>

    <section class="fm-card space-y-4">
        <div>
            <h2 class="fm-card-title">Inventory Actions</h2>
            <p class="fm-muted text-sm">Record stock activity and review inventory history.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('inventory.transactions.stock-in.create') }}"
               class="fm-btn fm-btn-primary">Receive Stock</a>
            <a href="{{ route('inventory.transactions.adjustment.create') }}"
               class="fm-btn fm-btn-secondary">Adjust Stock</a>
            <a href="{{ route('inventory.transactions.waste.create') }}"
               class="fm-btn fm-btn-secondary">Record Waste</a>
            <a href="{{ route('inventory.movements.index') }}"
               class="fm-btn fm-btn-secondary">Movement History</a>
            <a href="{{ route('inventory.ingredients.create') }}"
               class="fm-btn fm-btn-secondary">Add Ingredient</a>
        </div>
    </section>

    <div class="fm-grid fm-grid-2 lg:grid-cols-3 xl:grid-cols-3">
        <div class="fm-card">
            <p class="fm-muted text-sm">Total Ingredients</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format($totalIngredients) }}</p>
        </div>

        <div class="fm-card">
            <p class="fm-muted text-sm">Active Ingredients</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format($activeIngredients) }}</p>
        </div>

        <div class="fm-card">
            <p class="fm-muted text-sm">Low Stock</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format($lowStockIngredients) }}</p>
        </div>

        <div class="fm-card">
            <p class="fm-muted text-sm">Out of Stock</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format($outOfStockIngredients) }}</p>
        </div>

        <div class="fm-card">
            <p class="fm-muted text-sm">Stock Movements</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format($movementCount) }}</p>
        </div>

        <div class="fm-card">
            <p class="fm-muted text-sm">Total Stock Value</p>
            <p class="mt-2 text-2xl font-semibold">
                {{ "\u{20B1}" }}{{ number_format($stockValue, 2) }}
            </p>
            <p class="fm-muted mt-1 text-xs">Based on current quantity and average unit cost</p>
        </div>
    </div>

    <div class="fm-grid fm-grid-2">
        <section class="fm-card space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="fm-card-title">Low Stock Watchlist</h2>
                    <p class="fm-muted text-sm">Active ingredients at or below minimum stock.</p>
                </div>
                <span class="fm-muted text-sm">{{ number_format($lowStockIngredients) }} items</span>
            </div>

            @if($lowStockItems->isEmpty())
                <p class="fm-muted text-sm">No low-stock ingredients to display.</p>
            @else
                <div class="fm-table-wrap">
                    <table class="fm-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Current</th>
                                <th>Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowStockItems as $item)
                                <tr>
                                    <td class="font-medium">{{ $item->name }}</td>
                                    <td>
                                        {{ number_format((float) $item->current_stock, 3) }}
                                        {{ $item->base_unit }}
                                    </td>
                                    <td>
                                        {{ number_format((float) $item->minimum_stock, 3) }}
                                        {{ $item->base_unit }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="fm-card space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="fm-card-title">Recent Movements</h2>
                    <p class="fm-muted text-sm">Latest recorded inventory activity.</p>
                </div>
                <a href="{{ route('inventory.movements.index') }}"
                   class="fm-btn fm-btn-secondary">View All</a>
            </div>

            @if($recentMovements->isEmpty())
                <p class="fm-muted text-sm">No inventory movements recorded yet.</p>
            @else
                <div class="fm-table-wrap">
                    <table class="fm-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Movement</th>
                                <th>Quantity</th>
                                <th>Date / User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentMovements as $movement)
                                <tr>
                                    <td class="font-medium">
                                        {{ $movement->ingredient?->name ?? 'Unknown ingredient' }}
                                    </td>
                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $movement->movement_type)) }}
                                    </td>
                                    <td>
                                        {{ number_format((float) $movement->quantity, 3) }}
                                        {{ $movement->ingredient?->base_unit }}
                                    </td>
                                    <td>
                                        <span class="text-sm">{{ $movement->movement_at?->format('M d, Y') ?? '-' }}</span>
                                        <span class="fm-muted block text-xs">
                                            {{ $movement->user?->name ?? 'System' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="fm-card space-y-4">
        <div>
            <h2 class="fm-card-title">Ingredients</h2>
            <p class="fm-muted text-sm">Search and manage registered ingredients.</p>
        </div>

        <form method="GET"
              action="{{ route('inventory.index') }}"
              class="grid grid-cols-1 gap-3 sm:grid-cols-3">

            <div class="sm:col-span-2">
                <label for="search" class="fm-label">Search ingredients</label>
                <input id="search"
                       type="search"
                       name="search"
                       value="{{ $search }}"
                       class="fm-input"
                       placeholder="Name, unit, or storage location">
            </div>

            <div>
                <label for="status" class="fm-label">Status</label>
                <select id="status" name="status" class="fm-select">
                    <option value="all" @selected($status === 'all')>All</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="flex flex-wrap gap-2 sm:col-span-3">
                <button type="submit" class="fm-btn fm-btn-primary">Apply Filters</button>
                <a href="{{ route('inventory.index') }}" class="fm-btn fm-btn-secondary">Clear</a>
            </div>
        </form>

        <div class="fm-table-wrap">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Unit</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Unit Cost</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($ingredients as $ingredient)
                        <tr>
                            <td class="font-medium">{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->base_unit }}</td>
                            <td>{{ number_format((float) $ingredient->current_stock, 3) }}</td>
                            <td>{{ number_format((float) $ingredient->minimum_stock, 3) }}</td>
                            <td>{{ "\u{20B1}" }}{{ number_format((float) $ingredient->cost_per_base_unit, 2) }}</td>
                            <td>{{ $ingredient->storage_location ?: '—' }}</td>
                            <td>
                                @if($ingredient->is_active)
                                    <span class="fm-badge fm-badge-success">Active</span>
                                @else
                                    <span class="fm-badge fm-badge-neutral">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('inventory.ingredients.edit', $ingredient) }}"
                                       class="fm-btn fm-btn-secondary">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          action="{{ route('inventory.ingredients.status', $ingredient) }}"
                                          data-fm-confirm data-confirm-title="Change ingredient status" data-confirm-message="Are you sure you want to change this ingredient status?">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="fm-btn fm-btn-secondary">
                                            {{ $ingredient->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="fm-empty">
                                    No ingredients found.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="fm-muted text-sm">
                Showing {{ $ingredients->firstItem() ?? 0 }}
                to {{ $ingredients->lastItem() ?? 0 }}
                of {{ $ingredients->total() }} ingredients
            </p>

            <div>
                {{ $ingredients->links() }}
            </div>
        </div>
    </section>
</div>
@endsection