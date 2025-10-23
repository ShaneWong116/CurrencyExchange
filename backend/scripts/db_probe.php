<?php

// Quick DB probe: list key IDs to diagnose FK issues

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function listIds(string $table, string $cols = 'id') {
    $rows = DB::table($table)->selectRaw($cols)->orderBy('id')->limit(100)->get();
    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

echo "--- field_users (id, username, location_id) ---\n";
listIds('field_users', 'id, username, location_id');

echo "--- channels (id, name, code) ---\n";
listIds('channels', 'id, name, code');

echo "--- locations count ---\n";
$count = DB::table('locations')->count();
echo $count . PHP_EOL;

echo "--- transactions count ---\n";
$tcount = DB::table('transactions')->count();
echo $tcount . PHP_EOL;

echo "--- foreign_keys pragma ---\n";
$fk = DB::select('PRAGMA foreign_keys');
print_r($fk);


