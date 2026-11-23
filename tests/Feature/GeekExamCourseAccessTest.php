<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class GeekExamCourseAccessTest extends DeadlineFeatureCase
{
    private const PATH = '/api/geekexam/course-access';
    private const KEY = 'course-integration-test-secret-12345';

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_23_190000_create_geekexam_course_restrictions.php'))->up();
        config(['services.geekexam_course_access_secret' => self::KEY]);
    }

    private function sync(array $active = [], array $release = [], bool $reconcile = false,
        array $release_stale = [], string $key = self::KEY, ?int $stamp = null)
    {
        $stamp ??= now()->timestamp;
        $body = json_encode(compact('active', 'release', 'reconcile', 'release_stale'), JSON_THROW_ON_ERROR);
        return $this->call('POST', self::PATH, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EXAM_TIME' => (string) $stamp,
            'HTTP_X_EXAM_SIGNATURE' => hash_hmac('sha256', "POST\n".self::PATH."\n".$stamp."\n".$body, $key),
        ], $body);
    }

    public function test_only_the_signed_in_participant_loses_access_until_the_last_attempt_ends(): void
    {
        $student = User::findOrFail(1);
        $a = ['id' => (string) Str::uuid(), 'user_id' => $student->id];
        $b = ['id' => (string) Str::uuid(), 'user_id' => $student->id];
        $page = '/open/steps/1';
        $this->actingAs($student)->get($page)->assertOk()->assertSee('/open/course-material-access', false);
        $this->sync([$a, $b])->assertOk()->assertJsonCount(2, 'confirmed');
        $this->get($page)->assertForbidden()->assertSee('Вернуться в GeekExam')->assertDontSee('data-step-details-page', false);
        foreach (['/open/course-material-access', '/open/steps/1/notebook', '/insider/courses/1/steps/1',
            '/insider/courses/1/tasks/11/checker/files/1', '/insider/courses/1/planner/api/state',
            '/insider/code-submissions/'.$a['id'].'/files/1', '/insider/material-history/1',
            '/material-resources/'.$a['id'], '/shared/code-submissions/'.$a['id'].'/raw', '/legacy/geekpaste/file/zip'] as $url) {
            $response = $this->getJson($url)->assertForbidden()->assertJsonPath('error', 'strict_exam_active');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->postJson('/insider/courses/1/tasks/11/solution', ['text' => 'x'])->assertForbidden();
        $this->getJson('/insider/jwt')->assertUnprocessable()->assertJsonValidationErrors('redirect_url');

        $this->actingAs(User::findOrFail(3))->get($page)->assertOk();
        $this->actingAs(User::findOrFail(2))->get('/open/course-material-access')->assertNoContent();
        Auth::logout();
        $this->get($page)->assertOk()->assertDontSee('/open/course-material-access', false);
        $this->get('/open/course-material-access')->assertNoContent();
        $this->actingAs($student);
        $this->sync(release: [$a['id']])->assertOk();
        $this->get($page)->assertForbidden();
        $this->sync(release: [$b['id']])->assertOk();
        $this->sync([$b])->assertOk()->assertExactJson(['confirmed' => [], 'stale' => []]);
        $this->get($page)->assertOk();
    }

    public function test_stale_holds_wait_for_reconciliation_and_a_new_confirmation_wins_a_race(): void
    {
        $hold = ['id' => (string) Str::uuid(), 'user_id' => 1];
        $this->sync([$hold])->assertOk();
        $this->travel(10)->minutes();
        $this->actingAs(User::findOrFail(1))->getJson('/open/course-material-access')->assertForbidden();
        $this->sync(reconcile: true)->assertJsonPath('stale.0', $hold['id']);
        $this->sync([$hold])->assertJsonPath('confirmed.0', $hold['id']);
        $this->sync(release_stale: [$hold['id']])->assertOk();
        $this->getJson('/open/course-material-access')->assertForbidden();
        $this->travel(4)->minutes();
        $this->sync(release_stale: [$hold['id']])->assertOk();
        $this->get('/open/course-material-access')->assertNoContent();
    }

    public function test_unsigned_expired_malformed_or_conflicting_registration_cannot_lock_another_account(): void
    {
        $id = (string) Str::uuid();
        $hold = ['id' => $id, 'user_id' => 1];
        $this->sync([$hold], key: 'incorrect')->assertUnauthorized();
        $this->sync([$hold], stamp: now()->timestamp - 61)->assertUnauthorized();
        $this->sync([['id' => 'invalid', 'user_id' => 1]])->assertUnprocessable();
        $this->assertDatabaseCount('geekexam_course_restrictions', 0);
        $this->sync([$hold])->assertOk();
        $this->sync([['id' => $id, 'user_id' => 3]])->assertStatus(409);
        $this->actingAs(User::findOrFail(3))->get('/open/course-material-access')->assertNoContent();
        $earlyRelease = (string) Str::uuid();
        $this->sync(release: [$earlyRelease])->assertOk();
        $this->sync([['id' => $earlyRelease, 'user_id' => 3]])->assertJsonCount(0, 'confirmed');
    }
}
