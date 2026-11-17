<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\AddOnIngredient;
use App\Models\Ingredient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddOnIngredientRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private function makeAddOn(): AddOn
    {
        return AddOn::create([
            'name' => 'Test Extra Cheese',
            'description' => 'Test add-on',
            'price' => 35.00,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeIngredient(): Ingredient
    {
        return Ingredient::create([
            'name' => 'Test Cheddar Cheese',
            'base_unit' => 'kg',
            'current_stock' => 10.000,
            'minimum_stock' => 2.000,
            'cost_per_base_unit' => 420.0000,
            'is_active' => true,
        ]);
    }

    public function test_add_on_ingredient_mapping_can_be_created(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        $mapping = AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.125,
            'unit' => 'kg',
            'preparation_notes' => 'Grated cheese portion.',
        ]);

        $this->assertDatabaseHas('add_on_ingredients', [
            'id' => $mapping->id,
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => '0.125',
            'unit' => 'kg',
        ]);
    }

    public function test_mapping_has_correct_decimal_cast_and_relationships(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        $mapping = AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.125,
            'unit' => 'kg',
        ]);

        $mapping = $mapping->fresh();

        $this->assertSame('0.125', $mapping->quantity_required);
        $this->assertTrue($mapping->addOn->is($addOn));
        $this->assertTrue($mapping->ingredient->is($ingredient));
    }

    public function test_add_on_and_ingredient_many_to_many_relationships_work(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.030,
            'unit' => 'kg',
            'preparation_notes' => 'One standard topping portion.',
        ]);

        $addOn->refresh();
        $ingredient->refresh();

        $this->assertCount(1, $addOn->addOnIngredients);
        $this->assertCount(1, $addOn->ingredients);
        $this->assertCount(1, $ingredient->addOnIngredients);
        $this->assertCount(1, $ingredient->addOns);

        $this->assertEquals(
            0.030,
            (float) $addOn->ingredients->first()->pivot->quantity_required
        );

        $this->assertSame(
            'kg',
            $ingredient->addOns->first()->pivot->unit
        );
    }

    public function test_duplicate_add_on_ingredient_mapping_is_rejected(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.030,
            'unit' => 'kg',
        ]);

        $this->expectException(QueryException::class);

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.050,
            'unit' => 'kg',
        ]);
    }

    public function test_invalid_foreign_key_mapping_is_rejected(): void
    {
        $ingredient = $this->makeIngredient();

        $this->expectException(QueryException::class);

        AddOnIngredient::create([
            'add_on_id' => 999999,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.030,
            'unit' => 'kg',
        ]);
    }

    public function test_deleting_add_on_cascades_its_ingredient_mappings(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        $mapping = AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.030,
            'unit' => 'kg',
        ]);

        $addOn->delete();

        $this->assertDatabaseMissing('add_on_ingredients', [
            'id' => $mapping->id,
        ]);

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
        ]);
    }

    public function test_ingredient_cannot_be_deleted_while_mapping_exists(): void
    {
        $addOn = $this->makeAddOn();
        $ingredient = $this->makeIngredient();

        AddOnIngredient::create([
            'add_on_id' => $addOn->id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 0.030,
            'unit' => 'kg',
        ]);

        try {
            $ingredient->delete();
            $this->fail('Expected ingredient deletion to be restricted.');
        } catch (QueryException $exception) {
            $this->assertDatabaseHas('ingredients', [
                'id' => $ingredient->id,
            ]);
        }
    }
}