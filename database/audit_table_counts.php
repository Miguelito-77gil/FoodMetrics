<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $tables = DB::select("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");

    $totalRows = 0;

    echo PHP_EOL . "========================================" . PHP_EOL;
    echo " FOODMETRICS - EXACT ROW COUNT AUDIT" . PHP_EOL;
    echo "========================================" . PHP_EOL;

    foreach ($tables as $table) {
        $tableName = $table->TABLE_NAME;
        $safeName = '`' . str_replace('`', '``', $tableName) . '`';

        $result = DB::selectOne("SELECT COUNT(*) AS total FROM $safeName");
        $count = (int) $result->total;

        $totalRows += $count;

        echo sprintf("%-35s : %d rows", $tableName, $count) . PHP_EOL;
    }

    echo PHP_EOL . "========================================" . PHP_EOL;
    echo "TOTAL TABLES: " . count($tables) . PHP_EOL;
    echo "TOTAL DATABASE ROWS: " . $totalRows . PHP_EOL;
    echo "========================================" . PHP_EOL;

} catch (Throwable $e) {
    fwrite(STDERR, "AUDIT ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}