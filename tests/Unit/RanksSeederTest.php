<?php

namespace Tests\Unit;

use Database\Seeders\RanksSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RanksSeederTest extends TestCase
{
    private $originalDefaultConnection;
    private $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('ranks', function ($table) {
            $table->increments('id');
            $table->text('name');
            $table->integer('from');
            $table->integer('to');
            $table->text('icon')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ranks');
        DB::disconnect('sqlite');

        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
        ]);

        parent::tearDown();
    }

    public function testRanksSeederBackfillsMissingRanksAndUpdatesExistingRows()
    {
        DB::table('ranks')->insert([
            'name' => 'Рядовой',
            'from' => 999,
            'to' => 1000,
        ]);

        (new RanksSeeder())->run();
        (new RanksSeeder())->run();

        $this->assertSame(18, DB::table('ranks')->count());
        $this->assertSame([
            'from' => 0,
            'to' => 50,
        ], (array) DB::table('ranks')->where('name', 'Рядовой')->first(['from', 'to']));
        $this->assertTrue(DB::table('ranks')->where('name', 'Адмирал флота')->exists());
    }
}
