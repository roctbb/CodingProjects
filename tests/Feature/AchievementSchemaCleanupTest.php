<?php

namespace Tests\Feature;

use App\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AchievementSchemaCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        foreach (['users', 'courses', 'tasks', 'solutions'] as $name) {
            Schema::create($name, function ($table) {
                $table->increments('id');
            });
            DB::table($name)->insert(['id' => 1]);
        }
        require_once database_path('migrations/2026_05_10_170000_create_achievements_table.php');
        require_once database_path('migrations/2026_05_10_130000_create_course_activities_table.php');
        (new \CreateAchievementsTable())->up();
        (new \CreateCourseActivitiesTable())->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_14_120000_remove_legacy_achievement_visuals.php');
    }

    private function insertAchievement(?string $payload, int $userId = 1): int
    {
        return DB::table('achievements')->insertGetId([
            'user_id' => $userId, 'course_id' => 1, 'task_id' => 1, 'solution_id' => 1,
            'title' => 'Хранитель стека', 'description' => 'За собственный интерпретатор.',
            'icon_key' => 'code', 'payload' => $payload,
            'published_at' => '2026-10-13 10:00:00',
            'created_at' => '2026-10-13 10:00:00', 'updated_at' => '2026-10-13 10:00:00',
        ]);
    }

    public function testRemovesOnlyLegacyDataAndPreservesAwardsAndOtherActivities(): void
    {
        $keep = [
            'trophy_image' => 'achievement-trophies/achievement-1.png',
            'coin_reward' => 15, 'manual' => true,
            'extra' => ['empty' => new \stdClass(), 'svg_icon' => 'nested value'],
        ];
        $legacy = [
            'svg_icon' => '<svg/>', 'visual_key' => 'pipes', 'tone' => 'funny',
            'solution_source' => 'geekpaste', 'language' => 'python', 'model' => 'old-model',
            'prompt_version' => 2, 'trophy_prompt' => 'old prompt',
            'trophy_model' => 'old-image-model', 'trophy_generated_at' => null,
        ];
        $id = $this->insertAchievement(json_encode($keep + $legacy));
        $before = (array) DB::table('achievements')->find($id);
        $activityKeep = ['achievement_id' => $id, 'achievement_title' => 'Хранитель стека', 'achievement_description' => 'За собственный интерпретатор.'];
        $activityPayload = json_encode($activityKeep + ['icon_key' => 'code', 'svg_icon' => '<svg/>', 'visual_key' => 'pipes', 'trophy_image' => 'old.png']);
        foreach (['ai_achievement_earned', 'solution_checked'] as $type) {
            DB::table('course_activities')->insert([
                'type' => $type, 'course_id' => 1, 'task_id' => 1, 'user_id' => 1,
                'payload' => $activityPayload, 'updated_at' => '2026-10-13 10:00:00',
            ]);
        }

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('achievements', 'icon_key'));
        $this->assertNotContains('icon_key', (new Achievement())->getFillable());
        $after = (array) DB::table('achievements')->find($id);
        $this->assertEquals(json_decode(json_encode($keep)), json_decode($after['payload']));
        unset($before['icon_key'], $before['payload'], $after['payload']);
        $this->assertSame($before, $after);
        $activity = DB::table('course_activities')->where('type', 'ai_achievement_earned')->first();
        $this->assertSame($activityKeep, json_decode($activity->payload, true));
        $this->assertSame('2026-10-13 10:00:00', $activity->updated_at);
        $this->assertSame($activityPayload, DB::table('course_activities')->where('type', 'solution_checked')->value('payload'));
    }

    public function testCleansMultipleChunksAndCanBeRerun(): void
    {
        for ($id = 1; $id <= 205; $id++) {
            if ($id > 1) {
                DB::table('users')->insert(['id' => $id]);
            }
            $this->insertAchievement(json_encode(['svg_icon' => '<svg/>', 'coin_reward' => $id]), $id);
            DB::table('course_activities')->insert(['type' => 'ai_achievement_earned', 'payload' => '{"svg_icon":"<svg/>"}']);
        }

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(205, DB::table('achievements')->count());
        foreach (DB::table('achievements')->get() as $row) {
            $this->assertSame(['coin_reward' => $row->user_id], json_decode($row->payload, true));
        }
        $this->assertSame(205, DB::table('course_activities')->where('payload', '{}')->count());
    }

    public function testLeavesNullAndAlreadyCleanPayloadsUnchanged(): void
    {
        $this->insertAchievement(null);
        DB::table('course_activities')->insert([
            ['type' => 'ai_achievement_earned', 'payload' => null],
            ['type' => 'ai_achievement_earned', 'payload' => '{ "achievement_title": "Title", "extra": {} }'],
        ]);
        $before = DB::table('course_activities')->pluck('payload')->all();

        $this->migration()->up();

        $this->assertNull(DB::table('achievements')->value('payload'));
        $this->assertSame($before, DB::table('course_activities')->pluck('payload')->all());
    }

    public function testRollbackRestoresTheColumnWithADefaultWithoutInventingDeletedMetadata(): void
    {
        $this->insertAchievement('{"svg_icon":"<svg/>","coin_reward":15}');
        $this->migration()->up();
        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('achievements', 'icon_key'));
        $this->assertSame('sparkles', DB::table('achievements')->value('icon_key'));
        $this->assertSame(['coin_reward' => 15], json_decode(DB::table('achievements')->value('payload'), true));
    }

    public function testDroppingIconColumnPreservesTheUniqueAwardConstraint(): void
    {
        $this->insertAchievement(null);
        $this->migration()->up();
        $this->expectException(QueryException::class);
        DB::table('achievements')->insert([
            'user_id' => 1, 'course_id' => 1, 'task_id' => 1, 'title' => 'Duplicate', 'description' => 'Duplicate',
        ]);
    }
}
