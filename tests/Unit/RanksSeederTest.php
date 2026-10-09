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

    public function testNewScaleHasExactContiguousBoundaries()
    {
        (new RanksSeeder())->run();

        $ranks = DB::table('ranks')->orderBy('from')->get();
        $this->assertSame([
            0, 50, 150, 300, 700, 1100, 1500, 1900, 2300,
            2700, 3100, 3600, 4200, 4800, 5400, 6000, 8000, 11000,
        ], $ranks->pluck('from')->all());
        $this->assertSame(1000000, $ranks->last()->to);

        foreach ($ranks as $index => $rank) {
            $this->assertGreaterThan($rank->from, $rank->to);
            if ($index > 0) {
                $this->assertSame($ranks[$index - 1]->to, $rank->from);
            }
            foreach ([$rank->from, $rank->to - 1] as $score) {
                $this->assertSame(1, DB::table('ranks')->where('from', '<=', $score)->where('to', '>', $score)->count());
            }
        }
    }

    public function testAbbreviatedNamesAreUpdatedWithoutCreatingDuplicatesOrChangingIds()
    {
        $legacyRanks = [
            ['id' => 3, 'name' => 'Мл. сержант', 'from' => 150, 'to' => 300, 'icon' => 'old-icon'],
            ['id' => 5, 'name' => 'Ст. сержант', 'from' => 700, 'to' => 1500, 'icon' => null],
            ['id' => 8, 'name' => 'Ст. прапорщик', 'from' => 3500, 'to' => 4500, 'icon' => null],
            ['id' => 9, 'name' => 'Мл. лейтенант', 'from' => 4500, 'to' => 5500, 'icon' => null],
            ['id' => 11, 'name' => 'Ст. лейтенант', 'from' => 6500, 'to' => 7500, 'icon' => null],
        ];
        DB::table('ranks')->insert($legacyRanks);

        (new RanksSeeder())->run();
        (new RanksSeeder())->run();

        $this->assertSame(18, DB::table('ranks')->count());
        foreach ($legacyRanks as $rank) {
            $this->assertDatabaseHas('ranks', [
                'id' => $rank['id'], 'name' => $rank['name'], 'icon' => $rank['icon'],
            ]);
        }
        $this->assertDatabaseHas('ranks', ['id' => 8, 'from' => 1900, 'to' => 2300]);
        $this->assertDatabaseHas('ranks', ['id' => 9, 'from' => 2300, 'to' => 2700]);
        $this->assertDatabaseHas('ranks', ['id' => 11, 'from' => 3100, 'to' => 3600]);
    }

    public function testExistingDuplicateAliasesKeepTheirIdsAndReceiveIdenticalRanges()
    {
        DB::table('ranks')->insert([
            ['id' => 3, 'name' => 'Мл. сержант', 'from' => 150, 'to' => 300],
            ['id' => 19, 'name' => 'Младший сержант', 'from' => 100, 'to' => 300],
            ['id' => 11, 'name' => 'Ст. лейтенант', 'from' => 6500, 'to' => 7500],
            ['id' => 23, 'name' => 'Старший лейтенант', 'from' => 6500, 'to' => 7500],
        ]);

        (new RanksSeeder())->run();
        (new RanksSeeder())->run();

        $this->assertSame(20, DB::table('ranks')->count());
        foreach ([3, 19] as $id) {
            $this->assertDatabaseHas('ranks', ['id' => $id, 'from' => 150, 'to' => 300]);
        }
        foreach ([11, 23] as $id) {
            $this->assertDatabaseHas('ranks', ['id' => $id, 'from' => 3100, 'to' => 3600]);
        }
    }
}
