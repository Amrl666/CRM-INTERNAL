<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::select('SELECT column_default, data_type FROM information_schema.columns WHERE table_name = \'tasks\' AND column_name = \'status\'');
print_r($rows);
