<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Rank;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserExperienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        Notification::fake();

        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, rank_id INTEGER);
            CREATE TABLE ranks (id INTEGER PRIMARY KEY, name TEXT, "from" INTEGER, "to" INTEGER);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, task_id INTEGER, mark INTEGER);
            CREATE TABLE completed_courses (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, mark TEXT);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT);
            INSERT INTO ranks VALUES (1, 'First', 0, 100), (2, 'Second', 100, 200), (3, 'Third', 200, 10000);
            INSERT INTO users VALUES (1, 'Student', NULL), (2, 'Completed only', NULL), (3, 'Manual rank', 2), (4, 'No submissions', NULL);
            INSERT INTO solutions VALUES (1, 1, 10, 1, 5), (2, 1, 10, 1, 10), (3, 1, 20, 1, 8), (4, 1, 10, 2, 20), (5, 1, 10, 3, NULL), (6, 3, 10, 1, 5);
            INSERT INTO completed_courses VALUES (1, 1, 10, 'A'), (2, 1, 20, 'S'), (3, 2, 10, 'S'), (4, 3, 10, 'S');
            INSERT INTO coin_transactions VALUES (1, 1, 150, 'Award'), (2, 1, -30, 'Purchase');
            SQL);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testExperienceUsesOnlyBestMarkPerTaskIncludingCompletedCoursesTasks(): void
    {
        $this->assertSame(30, User::findOrFail(1)->score());
        $this->assertSame(0, User::findOrFail(2)->score());
        $this->assertSame(0, User::findOrFail(4)->score());
    }

    public function testManualRankOverrideIsPreserved(): void
    {
        $user = User::findOrFail(3);

        $this->assertSame(199, $user->score());
        $this->assertSame(2, $user->rank()->id);
    }

    public function testProfileListAndIndividualScoresAndRanksAgree(): void
    {
        $users = (new ProfileController())->index()->getData()['users'];

        foreach ($users as $user) {
            $individual = User::findOrFail($user->id);
            $this->assertSame($individual->score(), $user->score());
            $this->assertSame($individual->rank()->id, $user->rank()->id);
        }

        $this->assertSame(30, $users->firstWhere('id', 1)->score());
        $this->assertSame(0, $users->firstWhere('id', 2)->score());
    }

    public function testCommandReplacesOldCachesForAllChunksWithoutChangingStoredData(): void
    {
        for ($id = 5; $id <= 105; $id++) {
            DB::table('users')->insert(['id' => $id, 'name' => "User {$id}"]);
        }

        $before = [];
        foreach (['users', 'solutions', 'completed_courses', 'coin_transactions'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        foreach (User::all() as $user) {
            Cache::put("user:{$user->id}:score", 3230, 3600);
            Cache::put("user:{$user->id}:rank", Rank::findOrFail(3), 3600);
        }

        // Re-running the command must remain safe and must not grant rank bonuses.
        for ($run = 0; $run < 2; $run++) {
            $this->artisan('users:recalculate-experience')
                ->expectsOutput('Recalculated experience and ranks for 105 user(s).')
                ->assertSuccessful();

            foreach (User::all() as $user) {
                $expectedScore = match ($user->id) {
                    1 => 30,
                    3 => 199,
                    default => 0,
                };
                $this->assertSame($expectedScore, Cache::get("user:{$user->id}:score"));
                $this->assertSame($expectedScore, $user->score());
                $this->assertSame($user->id === 3 ? 2 : 1, $user->rank()->id);
            }
        }

        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        Notification::assertNothingSent();
    }
}
