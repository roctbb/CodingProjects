<?php

namespace Tests\Feature;

use App\Notifications\NewSolution;
use App\Solution;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeekPasteRecheckTest extends TestCase
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
        DB::table('course_teachers')->insert(['course_id' => 7, 'user_id' => 99]);
        DB::table('solutions')->insert(['id' => 1, 'user_id' => 42, 'course_id' => 7, 'task_id' => 12,
            'text' => 'https://paste.test/?id=TEST', 'submitted' => now(), 'checked' => now(),
            'mark' => 5, 'raw_mark' => 5, 'comment' => 'Original feedback', 'review_skipped' => true]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function payload(array $claims = []): array
    {
        return ['token' => JWT::encode(array_merge([
            'purpose' => 'solution-recheck', 'aud' => 'codingprojects', 'exp' => time() + 60,
            'user_id' => 42, 'task_id' => 12, 'course_id' => 7, 'solution' => 'https://paste.test/?id=TEST',
        ], $claims), str_repeat('test', 16), 'HS256'), 'comment' => 'Не согласен со вторым критерием'];
    }

    public function testStatusRequestAndRetryPreserveGradeAndNotifyOnce(): void
    {
        $this->postJson('/api/geekpaste/recheck/status', $this->payload())->assertOk()->assertJson(['available' => true, 'requested' => false]);
        Notification::assertNothingSent();
        $this->postJson('/api/geekpaste/recheck', $this->payload())->assertOk()->assertJson(['requested' => true, 'available' => false]);
        $this->postJson('/api/geekpaste/recheck', array_merge($this->payload(), ['comment' => 'Другой комментарий']))->assertOk();
        $solution = Solution::find(1);
        $this->assertSame('Не согласен со вторым критерием', $solution->recheck_comment);
        $this->assertSame(5, $solution->mark);
        $this->assertSame(5, $solution->raw_mark);
        $this->assertSame('Original feedback', $solution->comment);
        $this->assertFalse($solution->review_skipped);
        Notification::assertSentToTimes(User::find(99), NewSolution::class, 1);
        $this->postJson('/api/geekpaste/recheck/status', $this->payload())->assertJson(['requested' => true, 'comment' => $solution->recheck_comment]);
    }

    public function testRejectsInvalidExpiredOrWrongPurposeTokensAndOtherSolutions(): void
    {
        $this->postJson('/api/geekpaste/recheck', ['token' => 'invalid'])->assertStatus(401);
        foreach ([['exp' => time() - 100], ['purpose' => 'login'], ['aud' => 'other']] as $claims) {
            $this->postJson('/api/geekpaste/recheck', $this->payload($claims))->assertStatus(401);
        }
        foreach ([['user_id' => 43], ['task_id' => 13], ['course_id' => 8], ['solution' => 'https://paste.test/?id=OTHER']] as $claims) {
            $this->postJson('/api/geekpaste/recheck', $this->payload($claims))->assertNotFound();
        }
        Notification::assertNothingSent();
    }

    public function testRejectsEmptyShortAndLongComments(): void
    {
        foreach (['', 'short', str_repeat(' ', 20), str_repeat('a', 1001)] as $comment) {
            $this->postJson('/api/geekpaste/recheck', array_merge($this->payload(), ['comment' => $comment]))->assertStatus(422);
        }
        Notification::assertNothingSent();
    }

    public function testUnavailableForFullMarksPendingOrBlockedTasks(): void
    {
        foreach ([10, null] as $mark) {
            DB::table('solutions')->where('id', 1)->update(['mark' => $mark]);
            $this->postJson('/api/geekpaste/recheck', $this->payload())->assertStatus(409)->assertJson(['available' => false, 'requested' => false]);
        }
        DB::table('solutions')->where('id', 1)->update(['mark' => 5]);
        DB::table('blocked_tasks')->insert(['task_id' => 12, 'course_id' => 7, 'user_id' => 42]);
        $this->postJson('/api/geekpaste/recheck', $this->payload())->assertStatus(409);
        Notification::assertNothingSent();
    }

    public function testWebRequestAndApiShareDeduplication(): void
    {
        $this->withoutMiddleware();
        $this->actingAs((new User())->forceFill(['id' => 42, 'name' => 'Student', 'email' => 'student@example.test']));
        $this->post('/insider/courses/7/tasks/12/solution/1/recheck', ['recheck_comment' => 'Комментарий из CodingProjects'])->assertRedirect();
        $this->postJson('/api/geekpaste/recheck', $this->payload())->assertOk()->assertJson(['comment' => 'Комментарий из CodingProjects']);
        Notification::assertSentToTimes(User::find(99), NewSolution::class, 1);
    }

    public function testWebFormCannotRequestForAnotherAuthor(): void
    {
        $this->withoutMiddleware();
        $this->actingAs((new User())->forceFill(['id' => 43, 'name' => 'Other', 'email' => 'other@example.test']));
        $this->post('/insider/courses/7/tasks/12/solution/1/recheck', ['recheck_comment' => 'Чужое решение проверять нельзя'])->assertNotFound();
        Notification::assertNothingSent();
    }
}
