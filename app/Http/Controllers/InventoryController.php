<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Services\InventoryTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Ingredient::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        $ingredients = Ingredient::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('storage_location', 'like', "%{$search}%")
                        ->orWhere('base_unit', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'ingredients' => $ingredients,
            'search' => $search,
            'status' => $status,
            'totalIngredients' => Ingredient::count(),
            'activeIngredients' => Ingredient::where('is_active', true)->count(),
            'inactiveIngredients' => Ingredient::where('is_active', false)->count(),
            'lowStockIngredients' => Ingredient::where('is_active', true)
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'outOfStockIngredients' => Ingredient::where('is_active', true)
                ->where('current_stock', '<=', 0)
                ->count(),
            'movementCount' => InventoryMovement::count(),
            'stockValue' => (float) Ingredient::query()
                ->where('current_stock', '>', 0)
                ->sum(DB::raw('current_stock * cost_per_base_unit')),
            'lowStockItems' => Ingredient::query()
                ->where('is_active', true)
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->orderBy('current_stock')
                ->orderBy('name')
                ->limit(6)
                ->get(),
            'recentMovements' => InventoryMovement::query()
                ->with(['ingredient', 'user'])
                ->latest('movement_at')
                ->latest('id')
                ->limit(6)
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Ingredient::class);

        return view('inventory.ingredients.form', [
            'ingredient' => new Ingredient(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Ingredient::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:ingredients,name'],
            'base_unit' => ['required', 'string', 'max:20'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'cost_per_base_unit' => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'storage_location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($validated) {
            Ingredient::create([
                ...$validated,
                'current_stock' => 0,
                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Ingredient created successfully. Initial stock is zero.');
    }

    public function edit(Ingredient $ingredient): View
    {
        Gate::authorize('update', $ingredient);

        return view('inventory.ingredients.form', [
            'ingredient' => $ingredient,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        Gate::authorize('update', $ingredient);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('ingredients', 'name')->ignore($ingredient->id),
            ],
            'base_unit' => ['required', 'string', 'max:20'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'cost_per_base_unit' => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'storage_location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($ingredient, $validated) {
            $ingredient->update($validated);
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Ingredient details updated successfully.');
    }

    public function toggleStatus(Ingredient $ingredient): RedirectResponse
    {
        Gate::authorize('manageStatus', $ingredient);

        DB::transaction(function () use ($ingredient) {
            $lockedIngredient = Ingredient::query()
                ->lockForUpdate()
                ->findOrFail($ingredient->id);

            $lockedIngredient->update([
                'is_active' => ! $lockedIngredient->is_active,
            ]);
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Ingredient status updated successfully.');
    }

    public function createStockIn(): View
    {
        Gate::authorize('manageTransactions', Ingredient::class);

        return view('inventory.transactions.form', [
            'transactionType' => 'stock_in',
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeStockIn(
        Request $request,
        InventoryTransactionService $transactions
    ): RedirectResponse {
        Gate::authorize('manageTransactions', Ingredient::class);

        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $transactions->stockIn(
            (int) $validated['ingredient_id'],
            (int) $request->user()->id,
            (float) $validated['quantity'],
            (float) $validated['unit_cost'],
            $validated['reason']
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Stock received and inventory cost updated successfully.');
    }

    public function createAdjustment(): View
    {
        Gate::authorize('manageTransactions', Ingredient::class);

        return view('inventory.transactions.form', [
            'transactionType' => 'adjustment',
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeAdjustment(
        Request $request,
        InventoryTransactionService $transactions
    ): RedirectResponse {
        Gate::authorize('manageTransactions', Ingredient::class);

        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $transactions->adjust(
            (int) $validated['ingredient_id'],
            (int) $request->user()->id,
            $validated['direction'],
            (float) $validated['quantity'],
            $validated['reason']
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Stock adjustment recorded successfully.');
    }

    public function createWaste(): View
    {
        Gate::authorize('manageTransactions', Ingredient::class);

        return view('inventory.transactions.form', [
            'transactionType' => 'waste',
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeWaste(
        Request $request,
        InventoryTransactionService $transactions
    ): RedirectResponse {
        Gate::authorize('manageTransactions', Ingredient::class);

        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $transactions->recordWaste(
            (int) $validated['ingredient_id'],
            (int) $request->user()->id,
            (float) $validated['quantity'],
            $validated['reason']
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Ingredient waste recorded successfully.');
    }

    public function movements(Request $request): View
    {
        Gate::authorize('manageTransactions', Ingredient::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(['stock_in', 'stock_out', 'adjustment', 'waste'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $type = $validated['type'] ?? '';

        $movements = InventoryMovement::query()
            ->with(['ingredient', 'user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->whereHas('ingredient', function ($ingredientQuery) use ($search) {
                            $ingredientQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('movement_type', $type))
            ->when(
                ! empty($validated['from']),
                fn ($query) => $query->whereDate('movement_at', '>=', $validated['from'])
            )
            ->when(
                ! empty($validated['to']),
                fn ($query) => $query->whereDate('movement_at', '<=', $validated['to'])
            )
            ->latest('movement_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.movements.index', [
            'movements' => $movements,
            'search' => $search,
            'type' => $type,
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
        ]);
    }
}