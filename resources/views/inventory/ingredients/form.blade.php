@extends('layouts.app')

@section('title', $isEdit ? 'Edit Ingredient' : 'Add Ingredient')
@section('breadcrumb', 'Inventory')

@section('content')
<div class="fm-page space-y-6">

    <div class="fm-page-header">
        <a href="{{ route('inventory.index') }}"
           class="text-sm font-medium text-[var(--fm-olive)]">
            Back to Inventory
        </a>

        <h1 class="fm-page-title mt-3">
            {{ $isEdit ? 'Edit Ingredient' : 'Add Ingredient' }}
        </h1>

        <p class="fm-page-description">
            {{ $isEdit ? 'Update ingredient information without changing its stock balance.' : 'Register a new ingredient in the inventory.' }}
        </p>
    </div>


    <form method="POST"
          action="{{ $isEdit ? route('inventory.ingredients.update', $ingredient) : route('inventory.ingredients.store') }}"
          class="fm-card space-y-5">

        @csrf

        @if($isEdit)
            @method('PUT')
        @endif

        <div class="fm-grid fm-grid-2">
            <div>
                <label for="name" class="fm-label">Ingredient Name</label>
                <input id="name"
                       name="name"
                       type="text"
                       maxlength="150"
                       required
                       value="{{ old('name', $ingredient->name) }}"
                       class="fm-input">
            </div>

            <div>
                <label for="base_unit" class="fm-label">Base Unit</label>
                <input id="base_unit"
                       name="base_unit"
                       type="text"
                       maxlength="20"
                       required
                       value="{{ old('base_unit', $ingredient->base_unit) }}"
                       class="fm-input"
                       placeholder="kg, g, L, ml, pcs">
            </div>

            <div>
                <label for="minimum_stock" class="fm-label">Minimum Stock</label>
                <input id="minimum_stock"
                       name="minimum_stock"
                       type="number"
                       min="0"
                       max="999999999.999"
                       step="0.001"
                       required
                       value="{{ old('minimum_stock', $ingredient->minimum_stock ?? '0.000') }}"
                       class="fm-input">
            </div>

            <div>
                <label for="cost_per_base_unit" class="fm-label">Cost per Base Unit</label>
                <input id="cost_per_base_unit"
                       name="cost_per_base_unit"
                       type="number"
                       min="0"
                       max="99999999.9999"
                       step="0.0001"
                       required
                       value="{{ old('cost_per_base_unit', $ingredient->cost_per_base_unit ?? '0.0000') }}"
                       class="fm-input">
            </div>

            <div class="sm:col-span-2">
                <label for="storage_location" class="fm-label">Storage Location</label>
                <input id="storage_location"
                       name="storage_location"
                       type="text"
                       maxlength="100"
                       value="{{ old('storage_location', $ingredient->storage_location) }}"
                       class="fm-input"
                       placeholder="Example: Dry Storage">
            </div>

            <div class="sm:col-span-2">
                <label for="notes" class="fm-label">Notes</label>
                <textarea id="notes"
                          name="notes"
                          rows="4"
                          maxlength="5000"
                          class="fm-textarea">{{ old('notes', $ingredient->notes) }}</textarea>
            </div>
        </div>

        @if($isEdit)
            <div>
                <button
                    type="button"
                    class="fm-info-trigger"
                    data-fm-modal-trigger
                    data-modal-type="info"
                    data-modal-title="Current stock information"
                    data-modal-message="Current stock: {{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_unit }}. Stock balances can only be changed through inventory transactions."
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 11v5m0-8h.01"/>
                    </svg>
                    View stock information
                </button>
            </div>
        @else
            <div>
                <button
                    type="button"
                    class="fm-info-trigger"
                    data-fm-modal-trigger
                    data-modal-type="info"
                    data-modal-title="New ingredient information"
                    data-modal-message="New ingredients start with zero stock. Use the Stock In feature once it becomes available to record received supplies."
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 11v5m0-8h.01"/>
                    </svg>
                    View stock information
                </button>
            </div>
        @endif

        <div class="fm-divider"></div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="fm-btn fm-btn-primary">
                {{ $isEdit ? 'Save Changes' : 'Create Ingredient' }}
            </button>

            <a href="{{ route('inventory.index') }}" class="fm-btn fm-btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection