@extends('layouts.app')

@section('title', 'Inventory Movement History')

@section('content')
<div class="fm-page">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">Movement History</h1>
            <p class="fm-page-description">
                Review stock receipts, automatic deductions, adjustments, and recorded waste.
            </p>
        </div>

        <a href="{{ route('inventory.index') }}" class="fm-btn fm-btn-secondary">
            Back to Inventory
        </a>
    </div>

    <div class="fm-card">
        <form method="GET" action="{{ route('inventory.movements.index') }}"
              class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">

            <div class="lg:col-span-2">
                <label for="search" class="fm-label">Search</label>
                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="fm-input w-full"
                    placeholder="Ingredient or reason..."
                >
            </div>

            <div>
                <label for="type" class="fm-label">Movement Type</label>
                <select id="type" name="type" class="fm-select w-full">
                    <option value="">All movements</option>
                    <option value="stock_in" @selected($type === 'stock_in')>Stock In</option>
                    <option value="stock_out" @selected($type === 'stock_out')>Stock Out</option>
                    <option value="adjustment" @selected($type === 'adjustment')>Adjustment</option>
                    <option value="waste" @selected($type === 'waste')>Waste</option>
                </select>
            </div>

            <div>
                <label for="from" class="fm-label">From Date</label>
                <input
                    id="from"
                    type="date"
                    name="from"
                    value="{{ $from }}"
                    class="fm-input w-full"
                >
            </div>

            <div>
                <label for="to" class="fm-label">To Date</label>
                <input
                    id="to"
                    type="date"
                    name="to"
                    value="{{ $to }}"
                    class="fm-input w-full"
                >
            </div>

            <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
                <button type="submit" class="fm-btn fm-btn-primary">
                    Apply Filters
                </button>
                <a href="{{ route('inventory.movements.index') }}" class="fm-btn fm-btn-secondary">
                    Clear Filters
                </a>
            </div>
        </form>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="fm-card-title">Inventory Transactions</h2>
                <p class="fm-muted mt-1 text-sm">
                    {{ $movements->total() }} recorded movements
                </p>
            </div>
        </div>

        <div class="fm-table-wrap">
            <table class="fm-table min-w-[1050px]">
                <thead>
                    <tr>
                        <th>Date and Time</th>
                        <th>Ingredient</th>
                        <th>Movement</th>
                        <th>Quantity</th>
                        <th>Stock Before</th>
                        <th>Stock After</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Recorded By</th>
                        <th>Reason</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($movements as $movement)
                        @php
                            $movementLabel = match($movement->movement_type) {
                                'stock_in' => 'Stock In',
                                'stock_out' => 'Stock Out',
                                'adjustment' => 'Adjustment',
                                'waste' => 'Waste',
                                default => ucfirst(str_replace('_', ' ', $movement->movement_type)),
                            };
                        @endphp

                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="font-medium">
                                    {{ $movement->movement_at?->format('M d, Y') ?? '—' }}
                                </div>
                                <div class="fm-muted text-xs">
                                    {{ $movement->movement_at?->format('h:i A') ?? '' }}
                                </div>
                            </td>

                            <td class="font-medium">
                                {{ $movement->ingredient?->name ?? 'Deleted ingredient' }}
                            </td>

                            <td>
                                <span class="fm-badge">
                                    {{ $movementLabel }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap tabular-nums">
                                {{ number_format((float) $movement->quantity, 3) }}
                                {{ $movement->ingredient?->base_unit }}
                            </td>

                            <td class="whitespace-nowrap tabular-nums">
                                {{ number_format((float) $movement->stock_before, 3) }}
                            </td>

                            <td class="whitespace-nowrap tabular-nums font-medium">
                                {{ number_format((float) $movement->stock_after, 3) }}
                            </td>

                            <td class="whitespace-nowrap tabular-nums">
                                @if($movement->unit_cost !== null)
                                    {{ "\u{20B1}" }}{{ number_format((float) $movement->unit_cost, 4) }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="whitespace-nowrap tabular-nums">
                                @if($movement->total_cost !== null)
                                    {{ "\u{20B1}" }}{{ number_format((float) $movement->total_cost, 2) }}
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                {{ $movement->user?->name ?? 'System' }}
                            </td>

                            <td class="max-w-xs">
                                <span class="block whitespace-normal">
                                    {{ $movement->reason ?: '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center">
                                <div class="font-medium">No inventory movements found</div>
                                <p class="fm-muted mt-1 text-sm">
                                    Try changing your filters or record a new inventory transaction.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="fm-muted text-sm">
                Showing {{ $movements->firstItem() ?? 0 }}
                to {{ $movements->lastItem() ?? 0 }}
                of {{ $movements->total() }} movements
            </p>

            <div>
                {{ $movements->links() }}
            </div>
        </div>
    </div>
</div>
@endsection