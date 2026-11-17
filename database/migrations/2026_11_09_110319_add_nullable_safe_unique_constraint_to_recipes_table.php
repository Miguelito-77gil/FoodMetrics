<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE recipes
                ADD COLUMN variant_unique_key BIGINT UNSIGNED
                GENERATED ALWAYS AS (COALESCE(product_variant_id, 0)) STORED
                AFTER product_variant_id
            ");

            DB::statement("
                ALTER TABLE recipes
                DROP INDEX recipes_product_id_product_variant_id_unique,
                ADD UNIQUE INDEX recipes_product_variant_unique
                (product_id, variant_unique_key)
            ");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("
                DROP INDEX IF EXISTS recipes_product_id_product_variant_id_unique
            ");

            DB::statement("
                CREATE UNIQUE INDEX recipes_product_variant_unique
                ON recipes (product_id, COALESCE(product_variant_id, 0))
            ");

            return;
        }

        throw new RuntimeException(
            "Unsupported database driver for recipe uniqueness: {$driver}"
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE recipes
                DROP INDEX recipes_product_variant_unique,
                DROP COLUMN variant_unique_key,
                ADD UNIQUE INDEX recipes_product_id_product_variant_id_unique
                (product_id, product_variant_id)
            ");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("
                DROP INDEX IF EXISTS recipes_product_variant_unique
            ");

            DB::statement("
                CREATE UNIQUE INDEX recipes_product_id_product_variant_id_unique
                ON recipes (product_id, product_variant_id)
            ");

            return;
        }

        throw new RuntimeException(
            "Unsupported database driver for recipe uniqueness: {$driver}"
        );
    }
};