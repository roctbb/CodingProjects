<?php
// Local PostgreSQL integration check. Every table lives in a disposable, isolated schema.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\CoinTransaction;
use App\MarketGood;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$connection = config('database.connections.'.config('database.default'));
if (($connection['driver'] ?? null) !== 'pgsql' || !in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('This check requires a local PostgreSQL connection.');
}
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? ($argv[2] ?? '') : 'cp_business_test_'.bin2hex(random_bytes(8));
if (!preg_match('/^cp_business_test_[a-f0-9]{16}$/D', $schema)) throw new RuntimeException('Invalid isolated schema.');
$connection['schema'] = $schema;
$connection['search_path'] = $schema;
config(['database.default'=>'business_test', 'database.connections.business_test'=>$connection, 'cache.default'=>'array']);
DB::purge('business_test');
Notification::fake(); Queue::fake();

if ($worker) {
    while (microtime(true) < (float) $argv[7]) usleep(1000);
    $operation=$argv[3]; $user=(int)$argv[4]; $good=(int)$argv[5]; $key=$argv[6];
    $result=match ($operation) {
        'buy' => MarketGood::findOrFail($good)->buy(User::findOrFail($user),30,$key)?->id,
        'once' => CoinTransaction::registerOnce($user,7,'Concurrency reward')->id,
        'refund' => MarketGood::findOrFail($good)->cancelActiveAuctionBids(),
        default => throw new RuntimeException('Unknown operation'),
    };
    echo json_encode(['result'=>$result]), PHP_EOL;
    exit;
}

$assertions=0;
$check=function (bool $ok,string $message) use (&$assertions) {
    if (!$ok) throw new RuntimeException($message);
    $assertions++;
};
$race=function (array $operations) use ($schema) {
    $start=(string)(microtime(true)+1);
    $processes=array_map(fn ($op)=>new Process([PHP_BINARY,__FILE__,'worker',$schema,...$op,$start],dirname(__DIR__,2),null,null,30),$operations);
    foreach ($processes as $process) $process->start();
    foreach ($processes as $process) {
        $process->wait();
        if (!$process->isSuccessful()) throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }
};

DB::statement('CREATE SCHEMA "'.$schema.'"');
try {
    DB::unprepared(<<<'SQL'
        CREATE TABLE users (id serial PRIMARY KEY, name text, role text, created_at timestamp, updated_at timestamp);
        CREATE TABLE coin_transactions (id serial PRIMARY KEY, user_id integer, price integer, comment text, created_at timestamp, updated_at timestamp);
        CREATE TABLE courses (id serial PRIMARY KEY, name text, state text);
        CREATE TABLE course_students (course_id integer, user_id integer);
        CREATE TABLE course_teachers (course_id integer, user_id integer);
        CREATE TABLE market_goods (id serial PRIMARY KEY, name text, price integer, number integer, in_stock boolean, sale_type text, auction_finished_at timestamp, created_at timestamp, updated_at timestamp);
        CREATE TABLE market_deals (id serial PRIMARY KEY, good_id integer, user_id integer, price integer, source text, shipped boolean, purchase_key uuid, created_at timestamp, updated_at timestamp, UNIQUE(user_id,purchase_key));
        CREATE TABLE market_bids (id serial PRIMARY KEY, good_id integer, user_id integer, amount integer, created_at timestamp, updated_at timestamp, UNIQUE(good_id,user_id));
        CREATE TABLE course_activities (id serial PRIMARY KEY, course_id integer, user_id integer, type text, payload jsonb, created_at timestamp, updated_at timestamp);
        INSERT INTO users (id,name,role) VALUES (1,'First','student'),(2,'Second','student');
        INSERT INTO market_goods (id,name,price,number,in_stock,sale_type) VALUES (1,'One',30,2,true,'regular'),(2,'Two',30,2,true,'regular');
        SQL);
    $reset=function () {
        DB::table('market_deals')->delete(); DB::table('coin_transactions')->delete(); DB::table('market_bids')->delete();
        DB::table('market_goods')->update(['number'=>2,'sale_type'=>'regular']);
        foreach ([1,2] as $id) CoinTransaction::register($id,50,'Initial');
    };

    $reset();$key=(string)Str::uuid();
    $race([['buy','1','1',$key],['buy','1','1',$key]]);
    $check(DB::table('market_deals')->count()===1,'Retry created two orders');
    $check((int)User::find(1)->balance()===20,'Retry charged twice');
    $check((int)MarketGood::find(1)->number===1,'Retry consumed two inventory units');

    $reset();
    $race([['buy','1','1',(string)Str::uuid()],['buy','1','2',(string)Str::uuid()]]);
    $check(DB::table('market_deals')->count()===1,'Concurrent purchases overspent one wallet');
    $check((int)User::find(1)->balance()===20,'Wallet balance became negative');

    $reset();DB::table('market_goods')->where('id',1)->update(['number'=>1]);
    $race([['buy','1','1',(string)Str::uuid()],['buy','2','1',(string)Str::uuid()]]);
    $check(DB::table('market_deals')->count()===1,'Last inventory unit sold twice');
    $check((int)MarketGood::find(1)->number===0,'Inventory became negative');
    $check((int)DB::table('coin_transactions')->sum('price')===70,'Wrong total balance after inventory race');

    $reset();$race([['once','1','0',''],['once','1','0','']]);
    $check(DB::table('coin_transactions')->where('comment','Concurrency reward')->count()===1,'Reward issued twice');
    $check((int)User::find(1)->balance()===57,'Concurrent reward changed the balance twice');

    $reset();DB::table('market_goods')->where('id',1)->update(['sale_type'=>'auction']);
    DB::table('market_bids')->insert(['good_id'=>1,'user_id'=>1,'amount'=>30]);
    CoinTransaction::register(1,-30,'Held bid');
    $race([['refund','1','1',''],['refund','1','1','']]);
    $check(DB::table('market_bids')->count()===0,'Cancelled bids remain');
    $check((int)User::find(1)->balance()===50,'Concurrent cancellation refunded twice');
    echo "PostgreSQL concurrency passed: 5 races, {$assertions} assertions.\n";
} finally {
    DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
}
