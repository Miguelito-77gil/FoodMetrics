<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddOnIngredientMappingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedMappingFixtures(): array
    {
        $plan = [
            ['Extra Cheese', 'Cheddar Cheese', '0.035', 'kg'],
            ['Extra Rice', 'White Rice', '0.150', 'kg'],
            ['Garlic Rice', 'White Rice', '0.120', 'kg'],
            ['Garlic Rice', 'Garlic', '0.008', 'kg'],
            ['Garlic Rice', 'Cooking Oil', '0.010', 'L'],
            ['Fried Egg', 'Eggs', '1.000', 'pcs'],
            ['French Fries', 'Potatoes', '0.220', 'kg'],
            ['French Fries', 'Cooking Oil', '0.035', 'L'],
            ['French Fries', 'Salt', '0.002', 'kg'],
            ['Grilled Chicken', 'Chicken Breast', '0.120', 'kg'],
            ['Mushrooms', 'Mushrooms', '0.050', 'kg'],
            ['Shrimp', 'Shrimp', '0.100', 'kg'],
            ['Avocado', 'Avocado', '0.080', 'kg'],
        ];

        $addOnNames = array_unique(array_merge(
            array_column($plan, 0),
            ['Bacon', 'Extra Sauce', 'Vegetables']
        ));

        $addOnIds = [];

        foreach ($addOnNames as $name) {
            $addOnIds[$name] = DB::table('add_ons')->insertGetId([
                'name' => $name,
                'price' => 35.00,
                'is_available' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ingredientNames = array_unique(array_column($plan, 1));
        $ingredientIds = [];

        foreach ($ingredientNames as $name) {
            $unit = match ($name) {
                'Eggs' => 'pcs',
                'Cooking Oil' => 'L',
                default => 'kg',
            };

            $ingredientIds[$name] = DB::table('ingredients')->insertGetId([
                'name' => $name,
                'base_unit' => $unit,
                'current_stock' => 100.000,
                'minimum_stock' => 5.000,
                'cost_per_base_unit' => 10.0000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($plan as [$addOn, $ingredient, $quantity, $unit]) {
            DB::table('add_on_ingredients')->insert([
                'add_on_id' => $addOnIds[$addOn],
                'ingredient_id' => $ingredientIds[$ingredient],
                'quantity_required' => $quantity,
                'unit' => $unit,
                'preparation_notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'plan' => $plan,
            'addOnIds' => $addOnIds,
            'ingredientIds' => $ingredientIds,
        ];
    }

    public function test_all_thirteen_approved_mappings_have_exact_quantities_and_units(): void
    {
        $fixtures = $this->createApprovedMappingFixtures();

        $this->assertDatabaseCount('add_on_ingredients', 13);

        foreach ($fixtures['plan'] as [$addOn, $ingredient, $quantity, $unit]) {
            $mapping = DB::table('add_on_ingredients')
                ->where('add_on_id', $fixtures['addOnIds'][$addOn])
                ->where('ingredient_id', $fixtures['ingredientIds'][$ingredient])
                ->first();

            $this->assertNotNull(
                $mapping,
                "Missing mapping: {$addOn} -> {$ingredient}"
            );

            $this->assertEquals(
                (float) $quantity,
                (float) $mapping->quantity_required,
                "Incorrect quantity: {$addOn} -> {$ingredient}"
            );

            $this->assertSame(
                $unit,
                $mapping->unit,
                "Incorrect unit: {$addOn} -> {$ingredient}"
            );
        }
    }

    public function test_unresolved_add_ons_have_no_ingredient_mappings(): void
    {
        $fixtures = $this->createApprovedMappingFixtures();

        foreach (['Bacon', 'Extra Sauce', 'Vegetables'] as $name) {
            $count = DB::table('add_on_ingredients')
                ->where('add_on_id', $fixtures['addOnIds'][$name])
                ->count();

            $this->assertSame(
                0,
                $count,
                "{$name} should not have ingredient mappings yet."
            );
        }
    }

    public function test_garlic_rice_has_three_ingredient_relationships(): void
    {
        $fixtures = $this->createApprovedMappingFixtures();

        $garlicRice = \App\Models\AddOn::findOrFail(
            $fixtures['addOnIds']['Garlic Rice']
        );

        $ingredients = $garlicRice->ingredients;

        $this->assertCount(3, $ingredients);

        $this->assertEqualsCanonicalizing(
            ['White Rice', 'Garlic', 'Cooking Oil'],
            $ingredients->pluck('name')->all()
        );
    }
}