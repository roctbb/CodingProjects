<?php

namespace Tests\Feature;

use App\Notifications\NewSolution;
use App\Solution;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolutionRecheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'auth.jwt_secret' => str_repeat('test', 16)]);
        DB::purge('sqlite');
        Schema::create('solutions', function ($t) {
            $t->increments('id');
            foreach (['task_id', 'course_id', 'user_id', 'mark', 'raw_mark'] as $column) $t->integer($column)->nullable();
            $t->text('text');
            $t->text('comment')->nullable();
            $t->text('recheck_comment')->nullable();
            $t->boolean('recheck_requested')->default(false);
            $t->boolean('review_skipped')->default(false);
            $t->timestamp('submitted')->nullable();
            $t->timestamp('checked')->nullable();
            $t->timestamps();
        });
        Schema::create('tasks', function ($t) { $t->increments('id'); $t->boolean('is_code'); $t->integer('max_mark'); });
        Schema::create('blocked_tasks', function ($t) { $t->increments('id'); $t->integer('task_id'); $t->integer('course_id'); $t->integer('user_id'); });
        Schema::create('courses', function ($t) { $t->increments('id'); });
        Schema::create('users', function ($t) { $t->increments('id'); $t->string('name'); $t->string('email'); });
        Schema::create('course_teachers', function ($t) { $t->integer('course_id'); $t->integer('user_id'); $t->boolean('hidden_from_stats')->default(false); });
        DB::table('tasks')->insert(['id' => 12, 'is_code' => true, 'max_mark' => 10]);
        DB::table('courses')->insert(['id' => 7]);
        DB::table('users')->insert(['id' => 99, 'name' => 'Teacher', 'email' => 'teacher@example.test']);
        DB::table('users')->insert(['id' => 42, 'name' => 'Student', 'email' => 'student@example.test']);
        DB::table('course_teachers')->insert(['course_id' => 7, 'user_id' => 99]);
        DB::table('solutions')->insert(['id' => 1, 'user_id' => 42, 'course_id' => 7, 'task_id' => 12,
            'text' => 'https://paste.test/?id=TEST', 'submitted' => now(), 'checked' => now(),
            'mark' => 5, 'raw_mark' => 5, 'comment' => 'Original feedback', 'review_skipped' => true]);
        Schema::create('code_submissions', fn ($t) => $t->uuid('id')->primary());
        (require database_path('migrations/2026_11_21_180000_create_task_chat_messages.php'))->up();
        (require database_path('migrations/2026_11_22_001000_classify_task_chat_messages.php'))->up();
        (require database_path('migrations/2026_11_22_002000_add_tutor_chat_replies.php'))->up();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testRequestAndRetryPreserveGradeAndNotifyOnce(): void
    {
        $reviews=app(\App\Services\SolutionRecheck::class);
        $solution=Solution::findOrFail(1);
        $this->assertTrue($reviews->status($solution)['available']);
        $reviews->request($solution,'Не согласен со вторым критерием');
        $reviews->request($solution,'Другой комментарий');
        $solution->refresh();
        $this->assertSame(5,$solution->mark);$this->assertSame(5,$solution->raw_mark);
        $this->assertSame('Original feedback',$solution->comment);
        $this->assertDatabaseCount('task_chat_messages', 1);
        $this->assertSame('Не согласен со вторым критерием',$solution->recheck_comment);
        $this->assertFalse($solution->review_skipped);
        Notification::assertSentToTimes(User::find(99),NewSolution::class,1);
    }

    public function testUnavailableForFullMarksPendingOrBlockedTasks(): void
    {
        $reviews=app(\App\Services\SolutionRecheck::class);
        foreach ([10,null] as $mark) {
            DB::table('solutions')->where('id',1)->update(['mark'=>$mark,'raw_mark'=>$mark]);
            $this->assertFalse($reviews->status(Solution::findOrFail(1))['available']);
        }
        DB::table('solutions')->where('id',1)->update(['mark'=>5,'raw_mark'=>5]);
        DB::table('blocked_tasks')->insert(['task_id'=>12,'course_id'=>7,'user_id'=>42]);
        $this->assertFalse($reviews->status(Solution::findOrFail(1))['available']);
        Notification::assertNothingSent();
    }

    public function testWebFormCannotRequestForAnotherAuthor(): void
    {
        $this->withoutMiddleware();
        $this->actingAs((new User())->forceFill(['id' => 43, 'name' => 'Other', 'email' => 'other@example.test']));
        $this->post('/insider/courses/7/tasks/12/solution/1/recheck', ['recheck_comment' => 'Чужое решение проверять нельзя'])->assertNotFound();
        Notification::assertNothingSent();
    }
}
