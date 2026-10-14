<?php

namespace Tests\Feature;

use App\Achievement;
use App\CourseActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PulseAchievementImageTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'filesystems.default' => 'local']);
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, task_id INTEGER, status TEXT, payload TEXT, created_at DATETIME, updated_at DATETIME);
            CREATE TABLE course_activities (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, task_id INTEGER, type TEXT, payload TEXT, created_at DATETIME, updated_at DATETIME);');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function achievement(?string $path = null): Achievement
    {
        if ($path) Storage::put($path, base64_decode(self::PNG));
        return Achievement::create([
            'user_id' => 42, 'course_id' => 7, 'task_id' => 12, 'status' => Achievement::STATUS_PUBLISHED,
            'payload' => $path ? ['trophy_image' => $path] : [],
        ]);
    }

    private function activity(Achievement $achievement): CourseActivity
    {
        return CourseActivity::create([
            'user_id' => 42, 'course_id' => 7, 'task_id' => 12, 'type' => CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED,
            'payload' => ['achievement_id' => $achievement->id, 'achievement_title' => 'Ключник', 'achievement_description' => 'За квест'],
        ]);
    }

    private function render(CourseActivity $activity): string
    {
        return view('courses.partials.pulse-icon', compact('activity'))->render();
    }

    public function testPulseDisplaysGeneratedPngFromAchievementWithoutCopyingItIntoEvent(): void
    {
        $achievement = $this->achievement('achievement-trophies/trophy.png');
        $activity = $this->activity($achievement)->fresh(['achievement']);
        $html = $this->render($activity);
        $this->assertStringContainsString('<img ', $html);
        $this->assertStringContainsString(e($achievement->trophyImageUrl()), $html);
        $this->assertStringContainsString('pulse-feed__icon--image', $html);
        $this->assertStringNotContainsString('fa-trophy', $html);
        $this->assertArrayNotHasKey('trophy_image', $activity->payload);
    }

    public function testPendingImageAppearsOnNextLoadAndRegenerationUsesCurrentFile(): void
    {
        $achievement = $this->achievement();
        $activity = $this->activity($achievement);
        $this->assertStringContainsString('fa-trophy', $this->render($activity->fresh(['achievement'])));

        foreach (['achievement-trophies/first.png', 'achievement-trophies/replaced.png'] as $path) {
            Storage::put($path, base64_decode(self::PNG));
            $achievement->update(['payload' => ['trophy_image' => $path]]);
            $html = $this->render($activity->fresh(['achievement']));
            $this->assertStringContainsString($path, $html);
            $this->assertStringNotContainsString('fa-trophy', $html);
        }
        $this->assertStringNotContainsString('first.png', $html);
        $this->assertArrayNotHasKey('trophy_image', $activity->fresh()->payload);
    }

    public function testMissingFileHiddenOrDeletedAchievementShowsFallback(): void
    {
        $achievement = $this->achievement('achievement-trophies/trophy.png');
        $activity = $this->activity($achievement);
        Storage::delete('achievement-trophies/trophy.png');
        $this->assertStringContainsString('fa-trophy', $this->render($activity->fresh(['achievement'])));
        Storage::put('achievement-trophies/trophy.png', base64_decode(self::PNG));
        $achievement->update(['status' => Achievement::STATUS_HIDDEN]);
        $this->assertStringNotContainsString('<img ', $this->render($activity->fresh(['achievement'])));
        $achievement->delete();
        $this->assertStringContainsString('fa-trophy', $this->render($activity->fresh(['achievement'])));
    }

    public function testLegacySvgAndStaleEventImageAreNeverRendered(): void
    {
        $activity = new CourseActivity([
            'type' => CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED,
            'payload' => ['svg_icon' => '<svg onload="alert(1)"></svg>', 'trophy_image' => '/old-trophy.png'],
        ]);
        $html = $this->render($activity);
        $this->assertStringContainsString('fa-trophy', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('old-trophy.png', $html);
    }

    public function testMismatchedAchievementCannotSupplyAnImage(): void
    {
        $achievement = $this->achievement('achievement-trophies/trophy.png');
        foreach (['user_id', 'course_id', 'task_id'] as $field) {
            $activity = $this->activity($achievement);
            $activity->{$field} = 999;
            $activity->save();
            $this->assertNull($activity->fresh(['achievement'])->achievementImageUrl());
        }
        $activity->type = CourseActivity::TYPE_SOLUTION_SUBMITTED;
        $this->assertNull($activity->achievement_id);
        $this->assertStringNotContainsString('<img ', $this->render($activity));
    }

    public function testImagesAreLoadedInOneQueryForTheWholeFeed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->activity($this->achievement('achievement-trophies/trophy-' . $i . '.png'));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $activities = CourseActivity::with('achievement')->get();
        foreach ($activities as $activity) {
            $this->assertTrue($activity->relationLoaded('achievement'));
            $this->assertStringContainsString('<img ', $this->render($activity));
        }
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
