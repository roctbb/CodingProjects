<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LessonStickersTest extends TestCase
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
        Cache::flush();

        Schema::create('lessons', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('sticker')->nullable();
        });
        Schema::create('program_steps', function ($table) {
            $table->increments('id');
            $table->integer('lesson_id');
            $table->integer('sort_index')->default(0);
        });
        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->integer('step_id');
            $table->integer('sort_index')->default(0);
        });
        Schema::create('lesson_student_stats', function ($table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->integer('lesson_id');
            $table->float('percent');
        });
    }

    public function test_lessons_without_tasks_do_not_award_stickers_even_at_full_progress(): void
    {
        $this->lesson(1, 'No steps', 100);
        $this->lesson(2, 'Theory only', 100);
        DB::table('program_steps')->insert(['id' => 2, 'lesson_id' => 2]);

        $user = $this->student();

        $this->assertSame([], $user->getStickers()->all());
        $this->assertSame([], $user->getStickerDescriptions());
    }

    public function test_completed_lessons_with_tasks_still_award_stickers(): void
    {
        $this->lesson(1, 'Theory only', 100);
        $this->lesson(2, 'Completed practice', 100, true);
        $this->lesson(3, 'Incomplete practice', 90, true);
        $this->lesson(4, 'Another student', 100, true, 2);

        $user = $this->student();

        $this->assertSame(['/stickers/2.png'], $user->getStickers()->all());
        $this->assertSame(['/stickers/2.png' => 'Completed practice'], $user->getStickerDescriptions());
    }

    public function test_theory_only_lesson_does_not_replace_description_of_a_shared_earned_sticker(): void
    {
        $this->lesson(1, 'Completed practice', 100, true);
        $this->lesson(2, 'Theory only', 100);
        DB::table('lessons')->where('id', 2)->update(['sticker' => '/stickers/1.png']);

        $user = $this->student();

        $this->assertSame(['/stickers/1.png'], $user->getStickers()->all());
        $this->assertSame(['/stickers/1.png' => 'Completed practice'], $user->getStickerDescriptions());
    }

    public function test_completed_lesson_without_sticker_is_safe(): void
    {
        $this->lesson(1, 'Practice without sticker', 100, true);
        DB::table('lessons')->where('id', 1)->update(['sticker' => null]);
        $this->assertSame([], $this->student()->getStickers()->all());
        $this->assertSame([], $this->student()->getStickerDescriptions());
    }

    public function test_legacy_cached_empty_stickers_cannot_break_profile_rendering(): void
    {
        Cache::put('user:1:stickers', collect([null, '', '/stickers/1.png']), 60);
        $this->assertSame(['/stickers/1.png'], $this->student()->getStickers()->all());
    }

    private function lesson(int $id, string $name, int $percent, bool $hasTask = false, int $studentId = 1): void
    {
        DB::table('lessons')->insert(['id' => $id, 'name' => $name, 'sticker' => "/stickers/{$id}.png"]);
        DB::table('lesson_student_stats')->insert([
            'student_id' => $studentId, 'lesson_id' => $id, 'percent' => $percent,
        ]);
        if ($hasTask) {
            DB::table('program_steps')->insert(['id' => $id, 'lesson_id' => $id]);
            DB::table('tasks')->insert(['step_id' => $id]);
        }
    }

    private function student(): User
    {
        $user = new User();
        $user->id = 1;

        return $user;
    }
}
