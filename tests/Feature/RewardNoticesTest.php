<?php

namespace Tests\Feature;

use App\CoinTransaction;
use App\Services\RewardNotices;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RewardNoticesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Notification::fake();
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email TEXT, email_verified_at TEXT);
            INSERT INTO users VALUES (1,'Student','student','one@example.test','2026-01-01'),(2,'Other','student','two@example.test','2026-01-01'),(3,'Teacher','teacher','three@example.test','2026-01-01');
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, name TEXT, sticker TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, lesson_id INTEGER, sort_index INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER);
            CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, student_id INTEGER, lesson_id INTEGER, percent REAL);
            INSERT INTO lessons VALUES (1,'First','/stickers/1.png'),(2,'Same sticker','/stickers/1.png'),(3,'Theory','/stickers/3.png'),(4,'No sticker',null);
            INSERT INTO program_steps VALUES (1,1,1),(2,2,1),(3,4,1);
            INSERT INTO tasks VALUES (1,1),(2,2),(3,3);
            SQL);
        $this->migration()->up();
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_13_180000_create_reward_notices_table.php');
    }

    private function feed(): RewardNotices
    {
        return app(RewardNotices::class);
    }

    public function test_coin_notice_follows_saved_positive_transactions_and_is_consumed_only_once(): void
    {
        $transaction = CoinTransaction::registerOnce(1, 20, 'Task #1');
        CoinTransaction::registerOnce(1, 20, 'Task #1');
        $this->feed()->coins($transaction);
        CoinTransaction::register(1, -5, 'Good #2');
        CoinTransaction::register(1, 0, 'Nothing');
        CoinTransaction::register(3, 20, 'Teacher credit');
        $this->assertDatabaseCount('reward_notices', 1);
        $this->assertNull($this->feed()->claim(User::find(2)));
        $notice = $this->feed()->claim(User::find(1));
        $this->assertSame('coins', $notice['kind']);
        $this->assertSame(20, $notice['payload']['amount']);
        $this->assertSame('Награда за задачу #1', $notice['payload']['reason']);
        $this->assertNull($this->feed()->claim(User::find(1)));
        $this->assertNull($this->feed()->claim(User::find(3)));
    }

    public function test_rolled_back_credit_never_leaves_a_notice_or_a_coin(): void
    {
        try {
            DB::transaction(function () {
                CoinTransaction::register(1, 20, 'Rolled back');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('reward_notices', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function test_stickers_use_existing_award_threshold_and_deduplicate_across_courses_and_recalculations(): void
    {
        $rows = array_map(fn ($lesson) => ['course_id' => 1, 'student_id' => 1, 'lesson_id' => $lesson, 'percent' => 90], [1, 2, 3, 4]);
        $this->feed()->stickers($rows);
        $this->assertDatabaseCount('reward_notices', 0);
        $rows = array_map(fn ($row) => array_replace($row, ['percent' => 95]), $rows);
        $this->feed()->stickers($rows);
        $notice = $this->feed()->claim(User::find(1));
        $this->assertSame('sticker', $notice['kind']);
        $this->assertSame('/stickers/1.png', $notice['payload']['image']);
        $this->feed()->stickers(array_map(fn ($row) => array_replace($row, ['course_id' => 2]), $rows));
        $this->assertNull($this->feed()->claim(User::find(1)));
        $this->assertDatabaseCount('reward_notices', 1);
    }

    public function test_migration_baselines_old_stickers_without_showing_old_credits_or_collections(): void
    {
        $this->migration()->down();
        DB::table('lesson_student_stats')->insert([
            ['student_id' => 1, 'lesson_id' => 1, 'percent' => 100],
            ['student_id' => 1, 'lesson_id' => 2, 'percent' => 100],
            ['student_id' => 2, 'lesson_id' => 1, 'percent' => 90],
            ['student_id' => 1, 'lesson_id' => 3, 'percent' => 100],
        ]);
        $this->migration()->up();
        $this->assertDatabaseCount('reward_notices', 1);
        $this->feed()->stickers([['course_id' => 1, 'student_id' => 1, 'lesson_id' => 1, 'percent' => 100]]);
        $this->assertNull($this->feed()->claim(User::find(1)));
        $this->feed()->stickers([['course_id' => 1, 'student_id' => 2, 'lesson_id' => 1, 'percent' => 91]]);
        $this->assertSame('sticker', $this->feed()->claim(User::find(2))['kind']);
    }

    public function test_http_claim_requires_login_and_never_serves_another_students_notice(): void
    {
        $this->postJson('/insider/rewards/claim')->assertForbidden();
        CoinTransaction::register(2, 5, '<b>Gift</b>');
        $this->actingAs(User::find(1))->postJson('/insider/rewards/claim')->assertOk()->assertJsonPath('notice', null);
        $this->actingAs(User::find(2))->postJson('/insider/rewards/claim')->assertOk()->assertJsonPath('notice.payload.amount', 5);
        $this->postJson('/insider/rewards/claim')->assertOk()->assertJsonPath('notice', null)->assertHeader('Cache-Control', 'no-store, private');
    }
}
