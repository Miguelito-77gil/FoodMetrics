@extends('layouts.app')

@section('title', match($transactionType) {
    'stock_in' => 'Stock In',
    'adjustment' => 'Stock Adjustment',
    'waste' => 'Record Waste',
    default => 'Inventory Transaction',
})

@section('content')
@php
    $isStockIn = $transactionType === 'stock_in';
    $isAdjustment = $transactionType === 'adjustment';
    $isWaste = $transactionType === 'waste';

    $pageTitle = match($transactionType) {
        'stock_in' => 'Receive Stock',
        'adjustment' => 'Adjust Stock',
        'waste' => 'Record Ingredient Waste',
        default => 'Inventory Transaction',
    };

    $pageDescription = match($transactionType) {
        'stock_in' => 'Record incoming ingredients and update weighted average cost.',
        'adjustment' => 'Correct an ingredient balance with a documented reason.',
        'waste' => 'Record damaged, spoiled, or unusable ingredients.',
        default => 'Record an inventory transaction.',
    };

    $formAction = match($transactionType) {
        'stock_in' => route('inventory.transactions.stock-in.store'),
        'adjustment' => route('inventory.transactions.adjustment.store'),
        'waste' => route('inventory.transactions.waste.store'),
        default => route('inventory.index'),
    };
@endphp

<div class="fm-page">
    <div class="fm-page-header">
        <div>
            <a href="{{ route('inventory.index') }}" class="fm-muted text-sm">
                Inventory Management
            </a>

            <h1 class="fm-page-title mt-2">{{ $pageTitle }}</h1>
            <p class="fm-page-description">{{ $pageDescription }}</p>
        </div>

        <a href="{{ route('inventory.index') }}" class="fm-btn fm-btn-secondary">
            Back to Inventory
        </a>
    </div>

    <div class="fm-card max-w-3xl">
        <div class="mb-6">
            <h2 class="fm-card-title">Transaction Details</h2>
            <p class="fm-muted mt-1 text-sm">
                All submitted transactions are recorded in inventory movement history.
            </p>
        </div>

        <form method="POST" action="{{ $formAction }}" class="space-y-5">
            @csrf

            <div>
                <label for="ingredient_id" class="fm-label">Ingredient</label>
                <select
                    id="ingredient_id"
                    name="ingredient_id"
                    class="fm-select w-full"
                    required
                >
                    <option value="">Select an ingredient</option>
                    @foreach($ingredients as $ingredient)
                        <option
                            value="{{ $ingredient->id }}"
                            data-stock="{{ $ingredient->current_stock }}"
                            data-unit="{{ $ingredient->base_unit }}"
                            data-cost="{{ $ingredient->cost_per_base_unit }}"
                            @selected((string) old('ingredient_id') === (string) $ingredient->id)
                        >
                            {{ $ingredient->name }}
                        </option>
                    @endforeach
                </select>
                @error('ingredient_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div id="ingredient-summary" class="fm-card bg-[var(--fm-surface-muted)]">
                <p class="fm-muted text-sm">
                    Select an ingredient to view its current stock information.
                </p>
                <div id="ingredient-summary-details" class="mt-3 hidden grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="fm-muted text-xs">Current Stock</p>
                        <p id="current-stock-value" class="font-medium"></p>
                    </div>
                    <div>
                        <p class="fm-muted text-xs">Current Average Cost</p>
                        <p id="current-cost-value" class="font-medium"></p>
                    </div>
                </div>
            </div>

            @if($isAdjustment)
                <div>
                    <label for="direction" class="fm-label">Adjustment Direction</label>
                    <select
                        id="direction"
                        name="direction"
                        class="fm-select w-full"
                        required
                    >
                        <option value="">Select direction</option>
                        <option value="increase" @selected(old('direction') === 'increase')>
                            Increase Stock
                        </option>
                        <option value="decrease" @selected(old('direction') === 'decrease')>
                            Decrease Stock
                        </option>
                    </select>
                    @error('direction')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="quantity" class="fm-label">Quantity</label>
                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    class="fm-input w-full"
                    value="{{ old('quantity') }}"
                    min="0.001"
                    max="999999999.999"
                    step="0.001"
                    placeholder="0.000"
                    required
                >
                <p class="fm-muted mt-1 text-xs">
                    Enter the quantity using the ingredient's base unit.
                </p>
                @error('quantity')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($isStockIn)
                <div>
                    <label for="unit_cost" class="fm-label">
                        Purchase Cost per Base Unit
                    </label>
                    <input
                        type="number"
                        id="unit_cost"
                        name="unit_cost"
                        class="fm-input w-full"
                        value="{{ old('unit_cost') }}"
                        min="0"
                        max="99999999.9999"
                        step="0.0001"
                        placeholder="0.0000"
                        required
                    >
                    <p class="fm-muted mt-1 text-xs">
                        The system uses this purchase cost to recalculate weighted average cost.
                    </p>
                    @error('unit_cost')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="reason" class="fm-label">
                    Transaction Reason
                </label>
                <textarea
                    id="reason"
                    name="reason"
                    class="fm-textarea w-full"
                    rows="3"
                    maxlength="1000"
                    placeholder="{{ $isStockIn ? 'e.g. Supplier delivery, purchase order...' : ($isWaste ? 'e.g. Expired, spoiled, damaged...' : 'e.g. Physical stock count correction...') }}"
                    required
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="fm-divider"></div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('inventory.index') }}" class="fm-btn fm-btn-secondary">
                    Cancel
                </a>

                <button
                    type="submit"
                    class="fm-btn fm-btn-primary"
                    data-fm-confirm="{{ $isStockIn ? 'Confirm receiving this stock?' : ($isWaste ? 'Confirm recording this ingredient waste?' : 'Confirm this stock adjustment?') }}"
                >
                    {{ $isStockIn ? 'Record Stock In' : ($isWaste ? 'Record Waste' : 'Save Adjustment') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ingredientSelect = document.getElementById('ingredient_id');
    const details = document.getElementById('ingredient-summary-details');
    const stockValue = document.getElementById('current-stock-value');
    const costValue = document.getElementById('current-cost-value');

    if (!ingredientSelect || !details || !stockValue || !costValue) {
        return;
    }

    function updateIngredientSummary() {
        const option = ingredientSelect.options[ingredientSelect.selectedIndex];

        if (!option || !option.value) {
            details.classList.add('hidden');
            return;
        }

        const stock = Number(option.dataset.stock || 0);
        const cost = Number(option.dataset.cost || 0);
        const unit = option.dataset.unit || '';

        stockValue.textContent = stock.toFixed(3) + ' ' + unit;
        costValue.textContent = '\u20B1' + cost.toFixed(4) + ' / ' + unit;
        details.classList.remove('hidden');
    }

    ingredientSelect.addEventListener('change', updateIngredientSummary);
    updateIngredientSummary();
});
</script>
@endpush