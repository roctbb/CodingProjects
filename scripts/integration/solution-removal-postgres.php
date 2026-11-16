<?php
// Parse the DELETE on production PostgreSQL without executing it. No student data is changed.
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,get_class($e).': '.substr(explode(' (Connection:',$e->getMessage())[0],0,300).PHP_EOL);exit(1);});
use Illuminate\Support\Facades\DB;
if(DB::connection()->getDriverName()!=='pgsql' || DB::selectOne('SHOW default_transaction_read_only')->default_transaction_read_only!=='on')
    throw new RuntimeException('A read-only PostgreSQL connection is required.');
DB::select("EXPLAIN DELETE FROM reward_notices WHERE kind = 'xp' AND source_key IN (SELECT CAST(id AS TEXT) FROM code_execution_runs)");
echo json_encode(['passed'=>true,'postgresql_delete_plan'=>true,'executed_delete'=>false]).PHP_EOL;
