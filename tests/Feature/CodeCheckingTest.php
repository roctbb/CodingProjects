<?php

namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTaskChecker;
use App\CodeTestReport;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Grading;
use App\Services\CodeChecking\Reports;
use App\Services\CodeChecking\Integrity;
use App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodeCheckingTest extends TestCase
{
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('test', 16);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'auth.jwt_secret' => $this->secret, 'services.geekexecutor_secret' => $this->secret,
            'code_checking.ai_detection_enabled' => false]);
        DB::purge('sqlite');
        Storage::fake('code_checking'); Storage::fake('code_originals');
        Queue::fake();
        Notification::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, text TEXT, max_mark INTEGER, is_code BOOLEAN, is_hidden BOOLEAN, created_at TEXT, updated_at TEXT);
            CREATE TABLE blocked_tasks (task_id INTEGER, course_id INTEGER, user_id INTEGER);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT);
            INSERT INTO users VALUES (1, 'Teacher', 'teacher@test', 'teacher', '2026-01-01'), (2, 'Student', 'student@test', 'student', '2026-01-01'), (3, 'Other', 'other@test', 'student', '2026-01-01'), (4, 'Admin', 'admin@test', 'admin', '2026-01-01');
            INSERT INTO courses VALUES (7, 1), (8, 2);
            INSERT INTO course_teachers VALUES (7, 1);
            INSERT INTO course_students VALUES (7, 2), (7, 3);
            INSERT INTO program_steps VALUES (1, 1);
            INSERT INTO tasks (id, step_id, name, text, max_mark, is_code, is_hidden) VALUES (12, 1, 'Sum', 'Public task', 10, 1, 0);
            INSERT INTO coin_transactions (user_id, price, comment) VALUES (2, 20, 'Initial');
        SQL);
        (require database_path('migrations/2026_11_14_120000_create_code_checking_tables.php'))->up();
        (require database_path('migrations/2026_11_14_130000_expand_task_submissions.php'))->up();
        (require database_path('migrations/2026_11_14_140000_complete_native_submission_storage.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function login(int $id = 2): static
    {
        return $this->actingAs(User::findOrFail($id));
    }

    public function test_executor_browser_login_is_admin_only_and_scoped_to_fixed_callback(): void
    {
        config(['services.geekexecutor_public_url' => 'https://executor.test']);
        $url = '/insider/settings/code-executor/login?state='.str_repeat('a', 43).'&redirect_url=https://evil.test';
        $this->login()->get($url)->assertForbidden();
        $this->login(1)->get($url)->assertForbidden();
        $response = $this->login(4)->get($url)->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringStartsWith('https://executor.test/auth/callback#token=', $target);
        $this->assertNull(parse_url($target, PHP_URL_QUERY));
        parse_str(parse_url($target, PHP_URL_FRAGMENT), $fragment);
        $claims = JWT::decode($fragment['token'], new Key($this->secret, 'HS256'));
        $this->assertSame('executor-login', $claims->purpose);
        $this->assertSame('geekexecutor', $claims->aud);
        $this->assertSame('codingprojects', $claims->iss);
        $this->assertSame(4, $claims->user_id);
        $this->assertSame('admin', $claims->role);
        $this->assertSame(str_repeat('a', 43), $claims->state);
        $this->assertSame(60, $claims->exp - $claims->iat);
        $this->get('/insider/settings/code-executor/login?state=bad')->assertStatus(422);
        $this->get('/insider/settings/code-executor/login')->assertRedirect('https://executor.test/auth/login');
    }

    private function configBody(): array
    {
        return ['language' => 'python', 'check_type' => 'tests', 'private_instructions' => 'PRIVATE RUBRIC', 'test_config' => [
            'tests' => [['input' => " 2 3\n", 'output' => "5\n", 'is_public' => false]],
        ]];
    }

    public function test_task_attachments_starters_and_private_files_have_separate_access(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language' => 'python', 'check_type' => 'gpt',
            'submission_modes' => ['project'], 'private_instructions' => 'PRIVATE RUBRIC'])->assertOk();
        $starter = $this->postJson($base.'/checker/files', ['path' => 'lib/helper.py', 'kind' => 'starter',
            'is_public' => true, 'read_only' => true, 'content' => "  # starter\n"])->assertCreated()->json();
        $private = $this->postJson($base.'/checker/files', ['path' => 'criteria.txt', 'kind' => 'attachment',
            'is_public' => false, 'include_in_check' => true, 'content' => 'PRIVATE CRITERIA'])->assertCreated()->json();
        $this->login()->getJson($base.'/checker/files')->assertJsonCount(1, 'items')->assertDontSee('criteria.txt');
        $this->get($private['download_url'])->assertForbidden();
        $this->get($starter['download_url'])->assertStreamedContent("  # starter\n");
        $this->postJson($base.'/checker/files', ['path' => 'bad.txt', 'kind' => 'attachment', 'content' => 'x', 'is_public' => true])->assertForbidden();
        $this->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'mode' => 'project',
            'editor_files' => [['path' => 'main.py', 'content' => 'print(1)'], ['path' => 'lib/helper.py', 'content' => 'changed']]])->assertStatus(422);
        $submission = $this->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'mode' => 'project',
            'editor_files' => [['path' => 'main.py', 'content' => 'print(1)']]])->assertCreated()->json();
        $this->assertCount(2, $submission['files']);
        $run = CodeExecutionRun::findOrFail($submission['execution_id']);
        $this->assertSame('PRIVATE CRITERIA', app(Artifacts::class)->get($run->snapshot['task_files'][0]['name'] === 'criteria.txt'
            ? $run->snapshot['task_files'][0]['artifact']['blob_id'] : $run->snapshot['task_files'][1]['artifact']['blob_id']));
        $this->login(1)->deleteJson($base.'/checker/files/'.$starter['id'])->assertOk();
        $this->assertCount(2, $run->fresh()->snapshot['task_files']);
        $this->login()->getJson('/insider/code-submissions/'.$submission['id'])->assertOk()->assertDontSee('PRIVATE CRITERIA')->assertDontSee('PRIVATE RUBRIC');
    }

    public function test_project_draft_uses_optimistic_revision_and_can_be_submitted(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language' => 'python', 'check_type' => 'gpt',
            'submission_modes' => ['project'], 'private_instructions' => 'rubric'])->assertOk();
        $draft = ['revision' => 0, 'mode' => 'project', 'text' => "  Explanation\n", 'editor_files' => [
            ['path' => 'main.py', 'content' => "from lib.maths import value\nprint(value)\n"],
            ['path' => 'lib/maths.py', 'content' => "value = 5\n"], ['path' => 'lib/__init__.py', 'content' => '']]];
        $this->login()->getJson($base.'/code-draft')->assertJsonPath('revision', 0);
        $this->putJson($base.'/code-draft', $draft)->assertOk()->assertJsonPath('revision', 1)
            ->assertJsonPath('text', "  Explanation\n")->assertJsonPath('files.1.content', "value = 5\n");
        $this->putJson($base.'/code-draft', $draft)->assertConflict();
        $this->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'use_draft' => true, 'draft_revision' => 0])->assertConflict();
        $result = $this->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'use_draft' => true, 'draft_revision' => 1])
            ->assertCreated()->assertJsonPath('mode', 'project')->json();
        $this->assertCount(3, $result['files']);
        $this->login(3)->getJson($base.'/code-draft')->assertJsonPath('revision', 0);
        $draft['editor_files'][0]['path'] = '../escape.py';
        $this->login()->putJson($base.'/code-draft', array_replace($draft, ['revision' => 1]))->assertStatus(422);
    }

    public function test_multiple_uploads_and_format_constraints_are_owned_by_the_task(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language' => 'text', 'check_type' => 'gpt',
            'submission_modes' => ['files'], 'allowed_extensions' => ['xlsx', 'png'], 'max_files' => 2,
            'private_instructions' => 'Compare workbook and image'])->assertOk();
        $this->login()->post($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'mode' => 'files',
            'text' => 'Explanation', 'files' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('report.xlsx', 'workbook bytes'),
                \Illuminate\Http\UploadedFile::fake()->createWithContent('diagram.png', 'image bytes')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonCount(2, 'files')->assertJsonPath('language', 'text');
        $this->post($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'files' => [
            \Illuminate\Http\UploadedFile::fake()->createWithContent('unexpected.exe', 'binary')]], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_manual_text_answer_stays_local_and_grading_is_idempotent(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language' => 'text', 'check_type' => 'manual',
            'submission_modes' => ['text'], 'private_instructions' => 'Teacher notes'])->assertOk();
        Queue::fake();
        $result = $this->login()->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'text' => "  My answer\n"])
            ->assertCreated()->assertJsonPath('state', 'awaiting_review')->assertJsonPath('subscription', null)->assertJsonPath('text', "  My answer\n")->json();
        Queue::assertNotPushed(\App\Jobs\DispatchCodeExecution::class);
        $this->putJson('/insider/code-submissions/'.$result['id'].'/grade', ['points' => 3, 'comment' => 'Feedback'])->assertForbidden();
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        for ($i = 0; $i < 2; $i++) $this->login(1)->putJson('/insider/code-submissions/'.$result['id'].'/grade', ['points' => 3, 'comment' => 'Feedback'])->assertOk();
        $this->assertSame(1, CodeExecutionRun::find($result['execution_id'])->graded_by);
        $this->putJson('/insider/code-submissions/'.$result['id'].'/grade', ['points' => 4, 'comment' => 'Changed'])->assertConflict();
    }

    public function test_text_draft_keeps_uploaded_files_across_multipart_and_json_saves(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language'=>'text', 'check_type'=>'manual',
            'submission_modes'=>['text'], 'allowed_extensions'=>['png']])->assertOk();
        $this->login()->putJson($base.'/code-draft', ['revision'=>0,'mode'=>'text','text'=>'Original answer'])
            ->assertOk()->assertJsonPath('files.0.is_answer',true);
        $saved = $this->put($base.'/code-draft', ['revision'=>'1','mode'=>'text','text'=>'Updated answer',
            'files'=>[\Illuminate\Http\UploadedFile::fake()->createWithContent('drawing.png', "\x89PNG\0bytes")]])
            ->assertOk()->assertJsonPath('revision',2)->assertJsonPath('files.0.path','drawing.png')->json();
        $this->put($base.'/code-draft', ['revision'=>'2','draft_revision'=>'2','keep_files'=>['0'],
            'mode'=>'text','text'=>'Final answer'])->assertOk()->assertJsonPath('files.0.path','drawing.png')->assertJsonPath('revision',3);
        $result = $this->postJson($base.'/code-submissions', ['request_id'=>(string) Str::uuid(), 'use_draft'=>true,'draft_revision'=>3])
            ->assertCreated()->assertJsonPath('text','Final answer')->assertJsonCount(1,'files')->json();
        $this->get($result['files'][0]['download_url'])->assertOk()->assertStreamedContent("\x89PNG\0bytes");
        $this->put($base.'/code-draft', ['revision'=>'3','mode'=>'text','text'=>'Answer',
            'files'=>[\Illuminate\Http\UploadedFile::fake()->createWithContent('forbidden.exe','bytes')]])->assertStatus(422);
    }

    public function test_repository_capture_is_pinned_and_recheck_uses_the_saved_files(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language' => 'python', 'check_type' => 'gpt',
            'submission_modes' => ['repository'], 'max_files' => 100, 'private_instructions' => 'rubric'])->assertOk();
        $result = $this->login()->postJson($base.'/code-submissions', ['request_id' => (string) Str::uuid(), 'mode' => 'repository',
            'repository_url' => 'https://github.com/example/project.git', 'repository_ref' => 'main'])->assertCreated()->json();
        $path = tempnam(sys_get_temp_dir(), 'cp-repo-test-');
        $zip = new \ZipArchive(); $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('example-project-sha/main.py', "print(5)\n"); $zip->addFromString('example-project-sha/data.txt', 'fixture'); $zip->close();
        $repository = \Mockery::mock(\App\Services\CodeChecking\Repositories::class, [app(Artifacts::class)])->makePartial();
        $repository->shouldReceive('resolve')->once()->andReturn(str_repeat('a', 40));
        $repository->shouldReceive('archive')->once()->andReturn($path);
        $this->app->instance(\App\Services\CodeChecking\Repositories::class, $repository);
        $runner = \Mockery::mock(\App\Services\CodeChecking\RunnerClient::class);
        $runner->shouldReceive('dispatch')->twice();
        (new \App\Jobs\DispatchCodeExecution($result['execution_id']))->handle($runner);
        $saved = CodeSubmission::findOrFail($result['id']);
        $this->assertSame(str_repeat('a', 40), $saved->source_metadata['commit']);
        $this->assertCount(2, $saved->files);
        CodeExecutionRun::find($result['execution_id'])->update(['state' => 'completed', 'points' => 5, 'finished_at' => now()]);
        $next = $this->login(1)->postJson('/insider/code-submissions/'.$saved->id.'/recheck')->assertStatus(202)->json();
        (new \App\Jobs\DispatchCodeExecution($next['execution_id']))->handle($runner);
        $this->assertSame("print(5)\n", app(Artifacts::class)->get($saved->fresh()->files[0]['artifact']['blob_id']));
        $this->login()->get('/insider/code-submissions/'.$saved->id.'/repository/archive')->assertOk();
        $this->login(3)->get('/insider/code-submissions/'.$saved->id.'/repository/archive')->assertForbidden();
    }

    private function field(string $text): array
    {
        return ['type' => 'inline', 'text' => $text, 'bytes' => strlen($text), 'sha256' => hash('sha256', $text), 'truncated' => false];
    }

    private function legacyExport(string $checkType = 'gpt'): string
    {
        $root = sys_get_temp_dir().'/cp-legacy-'.Str::uuid();
        mkdir($root.'/blobs', 0700, true);
        $source = "  print(6)\n";
        $digest = hash('sha256', $source);
        file_put_contents($root.'/blobs/'.$digest, $source);
        $task = ['id' => 12, 'name' => 'Old task', 'lang' => 'python', 'points' => 10,
            'check_type' => $checkType, 'text' => 'OLD PRIVATE PROMPT', 'test_mode' => 'environment',
            'test_config' => null, 'gpt_model' => null, 'gpt_rate_limit' => 3];
        $solution = ['id' => 'LEGACY', 'task_id' => 12, 'course_id' => 7, 'user_id' => 2, 'lang' => 'python',
            'created_at' => '2020-01-02T03:04:05', 'checked_at' => '2020-01-02T03:05:06',
            'check_state' => 'done', 'check_points' => 6, 'check_comments' => 'OLD FEEDBACK',
            'source' => ['sha256' => $digest, 'bytes' => strlen($source), 'path' => 'blobs/'.$digest],
            'similarity_checked' => true, 'has_similarity_warning' => false, 'has_critical_similarity_warning' => false,
            'has_ai_warning' => true, 'ai_confidence' => 'low', 'ai_warning_reasons' => 'OLD WARNING', 'gpt_llm_probability' => 40];
        file_put_contents($root.'/tasks.jsonl', json_encode($task)."\n");
        file_put_contents($root.'/solutions.jsonl', json_encode($solution)."\n");
        file_put_contents($root.'/similarities.jsonl', '');
        $files = [];
        foreach (['tasks.jsonl', 'solutions.jsonl', 'similarities.jsonl'] as $name) $files[$name] = hash_file('sha256', $root.'/'.$name);
        file_put_contents($root.'/manifest.json', json_encode(['schema_version' => 1, 'source' => 'GeekPasteV2',
            'counts' => ['tasks' => 1, 'solutions' => 1, 'missing_original_archives' => 0], 'files' => $files]));
        return $root;
    }

    public function test_legacy_import_is_dry_by_default_repeatable_and_does_not_regrade(): void
    {
        DB::unprepared("CREATE TABLE solutions (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, task_id INTEGER, text TEXT, mark INTEGER, geekpaste_code_id TEXT, submitted TEXT, checked TEXT, created_at TEXT, updated_at TEXT);
            INSERT INTO solutions VALUES (99, 2, 7, 12, 'https://paste.geekclass.ru/?id=LEGACY', 4, 'LEGACY', '2020-01-02 03:04:05', '2020-01-02 03:05:06', '2020-01-02 03:04:05', '2020-01-02 03:05:06');");
        DB::statement('ALTER TABLE solutions ADD COLUMN geekpaste_integrity_dismissed_at TEXT');
        $root = $this->legacyExport();
        $import = app(\App\Services\CodeChecking\LegacyImport::class);
        $map = ['tasks' => ['12' => 12]];
        $summary = $import->run($root, $map);
        $this->assertSame([], $summary['issues']);
        $this->assertSame(0, CodeSubmission::count());
        $this->assertSame([], Storage::disk('code_checking')->allFiles());
        $import->run($root, $map, true, 4);
        $submission = CodeSubmission::firstOrFail();
        $this->assertSame(99, $submission->solution_id);
        $this->assertSame(6, CodeExecutionRun::firstOrFail()->points);
        $this->assertSame(4, DB::table('solutions')->where('id', 99)->value('mark'));
        $this->assertSame('2020-01-02 03:04:05', DB::table('solutions')->where('id', 99)->value('submitted'));
        $this->assertSame('2020-01-02 03:05:06', DB::table('solutions')->where('id', 99)->value('updated_at'));
        $this->assertStringContainsString('/insider/code-submissions/'.$submission->id, DB::table('solutions')->where('id', 99)->value('text'));
        $this->assertSame('2020-01-02 03:04:05', $submission->created_at->format('Y-m-d H:i:s'));
        $this->assertTrue(app(Integrity::class)->view($submission, true)['ai']['warning']);
        $this->login()->get('/insider/code-submissions/'.$submission->id.'/legacy-source')->assertStreamedContent("  print(6)\n");
        $this->login(3)->get('/insider/code-submissions/'.$submission->id.'/legacy-source')->assertForbidden();
        $this->assertSame(1, $import->run($root, $map, true, 4)['already_imported']);
        $this->assertSame(1, CodeSubmission::count());
        Queue::assertNotPushed(\App\Jobs\DispatchCodeExecution::class);
        Queue::assertNotPushed(\App\Jobs\CheckCodeIntegrity::class);
        $this->assertFileExists($root.'/solutions.jsonl');
    }

    public function test_legacy_environment_requires_conversion_before_native_recheck(): void
    {
        $root = $this->legacyExport('tests');
        $import = app(\App\Services\CodeChecking\LegacyImport::class);
        $this->assertNotEmpty($import->run($root, [])['issues']);
        $summary = $import->run($root, ['tasks' => ['12' => 12]], true, 4);
        $this->assertSame(1, $summary['needs_conversion']);
        $submission = CodeSubmission::firstOrFail();
        $this->login()->postJson('/insider/courses/7/tasks/12/code-submissions', ['request_id' => (string) Str::uuid(), 'code' => 'print(1)'])->assertConflict();
        $this->login(1)->postJson('/insider/code-submissions/'.$submission->id.'/recheck')->assertConflict();
        $this->putJson('/insider/courses/7/tasks/12/checker/private', ['language' => 'python', 'check_type' => 'gpt', 'private_instructions' => 'Converted'])->assertOk();
        $this->postJson('/insider/code-submissions/'.$submission->id.'/recheck', ['latest_configuration' => true])->assertStatus(202);
        $this->getJson('/insider/courses/7/tasks/12/checker/legacy')->assertOk()->assertSee('OLD PRIVATE PROMPT');
        $this->login()->getJson('/insider/courses/7/tasks/12/checker/legacy')->assertForbidden();
    }

    private function report(?string $run = null): array
    {
        return ['schema_version' => 1, 'run_id' => $run ?? (string) Str::uuid(), 'code_id' => 'LEGACY', 'user_id' => 2,
            'course_id' => 7, 'task_id' => 12, 'started_at' => 1000, 'finished_at' => 2000, 'test_mode' => 'io',
            'test_set_version' => str_repeat('a', 64), 'total' => 1, 'passed' => 0,
            'tests' => [['number' => 1, 'test_id' => str_repeat('b', 64), 'is_public' => false, 'verdict' => 'wrong_answer',
                'input' => $this->field('SECRET INPUT'), 'expected' => $this->field('SECRET EXPECTED'),
                'actual' => $this->field('SECRET ACTUAL'), 'error' => null, 'comparison' => [], 'limits' => []]]];
    }

    private function service(string $method, string $uri, array $data = [])
    {
        $body = $method === 'GET' ? '' : json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $token = JWT::encode(['iss' => 'geekexecutor', 'aud' => 'codingprojects', 'purpose' => match (true) {
            str_ends_with($uri, '/progress') => 'execution-progress', str_ends_with($uri, '/result') => 'execution-result',
            str_ends_with($uri, '/input') => 'execution-input', default => 'test-report',
        }, 'iat' => time(), 'exp' => time() + 60, 'body_sha256' => hash('sha256', $body)], $this->secret, 'HS256');
        return $this->call($method, $uri, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    private function createSubmission(): array
    {
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private', $this->configBody())->assertOk();
        return $this->login()->postJson('/insider/courses/7/tasks/12/code-submissions', [
            'request_id' => (string) Str::uuid(), 'code' => "  print(2+3)\n",
        ])->assertCreated()->json();
    }

    public function test_private_configuration_is_separate_and_whitespace_is_preserved(): void
    {
        $this->login()->getJson('/insider/courses/7/tasks/12/checker/private')->assertForbidden();
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private', $this->configBody())->assertOk();
        $public = $this->login()->getJson('/insider/courses/7/tasks/12/checker')->assertOk();
        $this->assertStringNotContainsString('PRIVATE RUBRIC', $public->getContent());
        $this->assertStringNotContainsString('test_config', $public->getContent());
        $field = CodeTaskChecker::find(12)->config['test_config']['tests'][0]['input'];
        $this->assertSame(" 2 3\n", app(Artifacts::class)->get($field['blob_id']));
        $this->assertStringNotContainsString('PRIVATE RUBRIC', CodeTaskChecker::find(12)->toJson());
    }

    public function test_submissions_keep_source_and_issue_only_scoped_socket_tokens(): void
    {
        $result = $this->createSubmission();
        $this->assertSame("  print(2+3)\n", $result['files'][0]['source']);
        $claims = JWT::decode($result['subscription']['token'], new Key($this->secret, 'HS256'));
        $this->assertSame('geekexecutor', $claims->aud);
        $this->assertSame('execution-subscribe', $claims->purpose);
        $this->assertSame($result['execution_id'], $claims->execution_id);
        $this->assertStringNotContainsString('PRIVATE RUBRIC', json_encode($result));
        $this->login(3)->getJson('/insider/code-submissions/'.$result['id'])->assertForbidden();
        $this->login(1)->getJson('/insider/code-submissions/'.$result['id'])->assertOk();
        $this->login()->get($result['files'][0]['download_url'])->assertOk()->assertStreamedContent("  print(2+3)\n");
    }

    public function test_submission_retries_are_idempotent_and_conflicting_payload_is_rejected(): void
    {
        $this->createSubmission();
        $record = CodeSubmission::firstOrFail();
        $body = ['request_id' => $record->request_id, 'code' => "  print(2+3)\n"];
        $this->login()->postJson('/insider/courses/7/tasks/12/code-submissions', $body)->assertCreated()->assertJson(['id' => $record->id]);
        $body['code'] = 'different';
        $this->postJson('/insider/courses/7/tasks/12/code-submissions', $body)->assertConflict();
        $this->assertDatabaseCount('code_submissions', 1);
        $this->assertDatabaseCount('code_execution_runs', 1);
    }

    public function test_purchase_unlocks_only_the_author_and_is_reused_in_another_run(): void
    {
        $report = app(Reports::class)->accept($this->report());
        $url = '/insider/code-test-reports/'.$report->id;
        $this->login()->getJson($url)->assertOk()->assertJsonPath('tests.0.unlocked', false)->assertDontSee('SECRET');
        $this->getJson($url.'/tests/'.str_repeat('b', 64).'/input')->assertForbidden();
        $this->postJson($url.'/tests/'.str_repeat('b', 64).'/purchase', ['expected_price' => 5])->assertOk()
            ->assertJson(['charged' => 5, 'balance' => 15])->assertJsonPath('report.tests.0.input.text', 'SECRET INPUT');
        $this->postJson($url.'/tests/'.str_repeat('b', 64).'/purchase')->assertOk()->assertJson(['charged' => 0]);
        $another = app(Reports::class)->accept($this->report());
        $this->getJson('/insider/code-test-reports/'.$another->id)->assertJsonPath('tests.0.unlocked', true);
        $this->login(3)->getJson($url)->assertForbidden();
        $this->login(1)->getJson($url)->assertJsonPath('tests.0.unlocked', true);
        $this->assertDatabaseCount('code_test_purchases', 1);
        $this->assertDatabaseCount('coin_transactions', 2);
    }

    public function test_failed_purchase_rolls_back_coins_and_never_unlocks(): void
    {
        $report = app(Reports::class)->accept($this->report());
        DB::unprepared("CREATE TRIGGER fail_purchase BEFORE INSERT ON code_test_purchases BEGIN SELECT RAISE(ABORT, 'failure'); END;");
        $this->login()->postJson('/insider/code-test-reports/'.$report->id.'/tests/'.str_repeat('b', 64).'/purchase')->assertStatus(500);
        $this->assertSame(20, (int) User::find(2)->balance());
        $this->assertDatabaseCount('code_test_purchases', 0);
    }

    public function test_insufficient_balance_changed_price_disabled_and_non_failure_are_not_charged(): void
    {
        $record = app(Reports::class)->accept($this->report());
        $url = '/insider/code-test-reports/'.$record->id.'/tests/'.str_repeat('b', 64).'/purchase';
        $this->login()->postJson($url, ['expected_price' => 6])->assertConflict();
        DB::table('coin_transactions')->update(['price' => 4]);
        $this->postJson($url)->assertConflict();
        DB::table('coin_transactions')->update(['price' => 20]);
        DB::table('blocked_tasks')->insert(['task_id' => 12, 'course_id' => 7, 'user_id' => 2]);
        $this->postJson($url)->assertConflict();
        $this->assertDatabaseCount('coin_transactions', 1);
    }

    public function test_signed_reports_preserve_spaces_are_immutable_and_reject_tampering(): void
    {
        $report = $this->report();
        $report['tests'][0]['actual'] = $this->field("  \n");
        $this->service('POST', '/api/executor/test-reports', $report)->assertOk();
        $this->service('POST', '/api/executor/test-reports', $report)->assertOk();
        $this->assertSame("  \n", CodeTestReport::first()->report['tests'][0]['actual']['text']);
        $report['tests'][0]['actual'] = $this->field('different');
        $this->service('POST', '/api/executor/test-reports', $report)->assertConflict();
        $this->postJson('/api/executor/test-reports', $report)->assertUnauthorized();
        $this->assertDatabaseCount('code_test_reports', 1);
    }

    public function test_progress_ignores_out_of_order_events_and_completed_results_are_idempotent(): void
    {
        $created = $this->createSubmission();
        $id = $created['execution_id'];
        $this->service('POST', '/api/executor/executions/'.$id.'/progress', ['sequence' => 2, 'phase' => 'testing', 'completed' => 7, 'total' => 20])->assertOk();
        $this->service('POST', '/api/executor/executions/'.$id.'/progress', ['sequence' => 1, 'phase' => 'compiling', 'completed' => 0, 'total' => 20])->assertOk();
        $this->assertSame('testing', CodeExecutionRun::find($id)->progress['phase']);
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        $result = ['execution_id' => $id, 'state' => 'done', 'points' => 10, 'comment' => 'OK'];
        $this->service('POST', '/api/executor/executions/'.$id.'/result', $result)->assertOk();
        $this->service('POST', '/api/executor/executions/'.$id.'/result', $result)->assertOk();
        $this->service('POST', '/api/executor/executions/'.$id.'/progress', ['sequence' => 3, 'phase' => 'preparing', 'completed' => 0, 'total' => 0])->assertOk();
        $this->assertSame('completed', CodeExecutionRun::find($id)->state);
    }

    public function test_plagiarism_compares_other_authors_and_hides_solution_ids_from_students(): void
    {
        $first = $this->createSubmission();
        $record = CodeSubmission::find($first['id']);
        $source = str_repeat('def sum_values(a, b): return a + b\n', 15);
        $record->update(['files' => [['name' => 'solution.py', 'artifact' => app(Artifacts::class)->put($source)]]]);
        app(Integrity::class)->check($record);
        $other = $record->replicate()->forceFill(['id' => (string) Str::uuid(), 'user_id' => 3, 'request_id' => (string) Str::uuid()]);
        $other->save();
        app(Integrity::class)->check($other);
        $this->assertTrue(app(Integrity::class)->view($other, true)['plagiarism']['warning']);
        $this->assertArrayNotHasKey('matches', app(Integrity::class)->view($other, false)['plagiarism']);
        $url = '/insider/code-submissions/'.$other->id.'/integrity/review';
        $this->login(3)->putJson($url, ['dismissed' => true])->assertForbidden();
        $this->login(1)->putJson($url, ['dismissed' => true, 'comment' => 'PRIVATE REVIEW'])->assertOk();
        $this->assertFalse(app(Integrity::class)->legacyPayload($other)['similarity']['warning']);
        $this->login(3)->getJson('/insider/code-submissions/'.$other->id)->assertDontSee('PRIVATE REVIEW');
        $this->login(1)->putJson($url, ['dismissed' => false])->assertOk();
        $this->assertTrue(app(Integrity::class)->legacyPayload($other)['similarity']['critical']);
    }

    public function test_bulk_recheck_queues_only_latest_submission_per_student(): void
    {
        $first = $this->createSubmission();
        $second = $this->createSubmission();
        $third = $this->login(3)->postJson('/insider/courses/7/tasks/12/code-submissions', [
            'request_id' => (string) Str::uuid(), 'code' => 'print(5)',
        ])->assertCreated()->json();
        CodeExecutionRun::query()->update(['state' => 'completed', 'finished_at' => now()]);
        $this->mock(Grading::class)->shouldNotReceive('apply');
        $response = $this->login(1)->postJson('/insider/courses/7/tasks/12/code-submissions/recheck-latest')->assertStatus(202);
        $this->assertCount(2, $response->json('execution_ids'));
        $this->assertSame($first['execution_id'], CodeSubmission::find($first['id'])->current_run_id);
        foreach ([$second, $third] as $item) $this->assertNotEquals($item['execution_id'], CodeSubmission::find($item['id'])->current_run_id);
        $this->assertDatabaseCount('code_submissions', 3);
        $this->assertDatabaseCount('code_execution_runs', 5);
        $this->assertDatabaseCount('coin_transactions', 1);
    }

    public function test_large_test_fields_are_private_and_downloadable_after_purchase(): void
    {
        $report = $this->report();
        $text = str_repeat('private test ', 10000);
        $report['tests'][0]['input'] = app(Artifacts::class)->put($text);
        $record = app(Reports::class)->accept($report);
        $url = '/insider/code-test-reports/'.$record->id;
        $this->login()->getJson($url)->assertDontSee('blob_id')->assertDontSee('download_url')->assertDontSee('private test');
        $response = $this->postJson($url.'/tests/'.str_repeat('b', 64).'/purchase')->assertOk();
        $download = $response->json('report.tests.0.input.download_url');
        $this->get($download)->assertOk()->assertStreamedContent($text);
        $this->login(3)->get($download)->assertForbidden();
    }

    public function test_failed_grading_rolls_back_the_result_for_safe_callback_retry(): void
    {
        $created = $this->createSubmission();
        $id = $created['execution_id'];
        $this->mock(Grading::class)->shouldReceive('apply')->once()->andThrow(new \RuntimeException('temporary failure'));
        $result = ['execution_id' => $id, 'state' => 'done', 'points' => 10, 'comment' => 'OK'];
        $this->service('POST', '/api/executor/executions/'.$id.'/result', $result)->assertStatus(500);
        $this->assertNull(CodeExecutionRun::find($id)->result_hash);
        $this->assertNull(CodeExecutionRun::find($id)->finished_at);
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        $this->service('POST', '/api/executor/executions/'.$id.'/result', $result)->assertOk();
    }

    public function test_execution_error_never_applies_a_zero_grade_and_can_be_retried(): void
    {
        $created = $this->createSubmission();
        $this->mock(Grading::class)->shouldNotReceive('apply');
        $result = ['execution_id' => $created['execution_id'], 'state' => 'execution error', 'points' => 0, 'comment' => 'Service unavailable'];
        $this->service('POST', '/api/executor/executions/'.$created['execution_id'].'/result', $result)->assertOk();
        $this->assertSame('error', CodeExecutionRun::find($created['execution_id'])->state);
        $this->login(1)->postJson('/insider/code-submissions/'.$created['id'].'/recheck')->assertStatus(202);
        $this->assertNotEquals($created['execution_id'], CodeSubmission::find($created['id'])->current_run_id);
    }

    public function test_dispatch_lease_prevents_duplicate_dispatch_and_is_released_on_failure(): void
    {
        $created = $this->createSubmission();
        $run = CodeExecutionRun::find($created['execution_id']);
        $run->update(['dispatch_lease_until' => now()->addMinutes(5)]);
        $client = $this->mock(\App\Services\CodeChecking\RunnerClient::class);
        $client->shouldNotReceive('dispatch');
        $job = new \App\Jobs\DispatchCodeExecution($run->id);
        $job->handle($client);
        $job->failed(new \RuntimeException('Another delivery is still running'));
        $this->assertNull($run->fresh()->finished_at);
        $run->update(['dispatch_lease_until' => now()->subMinute()]);
        $client = $this->mock(\App\Services\CodeChecking\RunnerClient::class);
        $client->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('offline'));
        try { $job->handle($client); $this->fail('Dispatch should fail'); } catch (\RuntimeException $e) { $this->assertSame('offline', $e->getMessage()); }
        $this->assertNull($run->fresh()->dispatch_lease_until);
        $this->assertNull($run->fresh()->dispatched_at);
    }

    public function test_execution_can_download_only_the_signed_artifact_and_signature_covers_exact_body(): void
    {
        $created = $this->createSubmission();
        $input = $this->service('GET', '/api/executor/executions/'.$created['execution_id'].'/input')->assertOk()->json();
        $url = $input['files'][0]['artifact']['download_url'];
        $this->get($url)->assertOk()->assertStreamedContent("  print(2+3)\n");
        $wrong = str_replace($input['files'][0]['artifact']['blob_id'], str_repeat('f', 64), $url);
        $this->get($wrong)->assertUnauthorized();
        $body = json_encode($this->report());
        $token = JWT::encode(['iss' => 'geekexecutor', 'aud' => 'codingprojects', 'purpose' => 'test-report',
            'iat' => time(), 'exp' => time() + 60, 'body_sha256' => str_repeat('0', 64)], $this->secret, 'HS256');
        $this->call('POST', '/api/executor/test-reports', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body)->assertUnauthorized();
    }

    public function test_gpt_limits_and_extra_attempts_are_owned_by_coding_projects(): void
    {
        $body = ['language' => 'python', 'check_type' => 'gpt', 'private_instructions' => 'Rubric', 'gpt_rate_limit' => 1];
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private', $body)->assertOk();
        $this->login()->postJson('/insider/courses/7/tasks/12/code-submissions', ['request_id' => (string) Str::uuid(), 'code' => 'print(1)'])->assertCreated();
        $attempt = ['request_id' => (string) Str::uuid(), 'code' => 'print(2)'];
        $this->postJson('/insider/courses/7/tasks/12/code-submissions', $attempt)->assertStatus(429);
        $this->postJson('/insider/courses/7/tasks/12/checker/extra-attempt')->assertOk()->assertJson(['charged' => 5]);
        $this->postJson('/insider/courses/7/tasks/12/checker/extra-attempt')->assertOk()->assertJson(['charged' => 0]);
        $this->postJson('/insider/courses/7/tasks/12/code-submissions', $attempt)->assertCreated();
        $this->postJson('/insider/courses/7/tasks/12/code-submissions', ['request_id' => (string) Str::uuid(), 'code' => 'print(3)'])->assertStatus(429);
        $this->assertSame(15, (int) User::find(2)->balance());
    }

    public function test_form_submission_preserves_indentation_and_malformed_json_is_rejected(): void
    {
        $this->createSubmission();
        $response = $this->login()->post('/insider/courses/7/tasks/12/code-submissions', [
            'request_id' => (string) Str::uuid(), 'code' => "  indented\n",
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame("  indented\n", $response->json('files.0.source'));
        $this->call('POST', '/insider/courses/7/tasks/12/code-submissions', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{')->assertUnprocessable();
        $this->getJson('/insider/code-submissions/not-a-uuid')->assertNotFound();
    }

    public function test_executor_can_use_a_secret_separate_from_legacy_sso(): void
    {
        $separate = str_repeat('executor', 8);
        config(['services.geekexecutor_secret' => $separate]);
        $created = $this->createSubmission();
        $claims = JWT::decode($created['subscription']['token'], new Key($separate, 'HS256'));
        $this->assertSame($created['execution_id'], $claims->execution_id);
        $this->service('GET', '/api/executor/executions/'.$created['execution_id'].'/input')->assertUnauthorized();
    }
    private function rewriteLegacy(string $root,array $changes=[],?string $source=null): array
    {
        $row=json_decode(trim(file_get_contents($root.'/solutions.jsonl')),true);
        $row=array_replace($row,$changes);
        if($source!==null){$sha=hash('sha256',$source);file_put_contents($root.'/blobs/'.$sha,$source);$row['source']=['sha256'=>$sha,'bytes'=>strlen($source),'path'=>'blobs/'.$sha];}
        file_put_contents($root.'/solutions.jsonl',json_encode($row)."\n");
        $manifest=json_decode(file_get_contents($root.'/manifest.json'),true);
        foreach(['tasks.jsonl','solutions.jsonl','similarities.jsonl'] as $name)$manifest['files'][$name]=hash_file('sha256',$root.'/'.$name);
        file_put_contents($root.'/manifest.json',json_encode($manifest));
        return $row;
    }

    public function test_import_uses_each_submissions_language_and_conflicts_are_visible_before_writes(): void
    {
        $root=$this->legacyExport();$this->rewriteLegacy($root,['lang'=>'cpp'],"#include <iostream>\nint main(){}\n");
        $import=app(\App\Services\CodeChecking\LegacyImport::class);$map=['tasks'=>['12'=>12]];
        $import->run($root,$map,true,4);
        $submission=CodeSubmission::firstOrFail();
        $this->assertSame('cpp',$submission->language);$this->assertSame('cpp',$submission->currentRun->snapshot['language']);
        $this->assertSame('python',CodeTaskChecker::find(12)->config['language']);
        $old=$submission->current_run_id;
        $this->rewriteLegacy($root,['check_points'=>10]);
        $this->assertStringContainsString('changed_imported_solution',implode(' ',$import->run($root,$map)['issues']));
        try {$import->run($root,$map,true,4);$this->fail('Conflict must block apply');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('preflight',$e->getMessage());}
        $this->assertSame($old,$submission->fresh()->current_run_id);
        $import->run($root,$map,true,4,'UTC',true);
        $this->assertSame(2,CodeExecutionRun::count());$this->assertSame(6,CodeExecutionRun::find($old)->points);
        $this->assertSame(10,$submission->fresh()->currentRun->points);
        $this->assertSame(1,DB::table('coin_transactions')->count());
        Queue::assertNothingPushed();
    }

    public function test_import_preserves_long_original_names_and_flags_missing_bytes_without_fake_empty_files(): void
    {
        $root=$this->legacyExport();$name=str_repeat('я',125).'.png';$bytes="\x89PNG\0old-original";
        $this->rewriteLegacy($root,['lang'=>'image','viewed_by_teacher'=>true,'available_without_auth'=>true],json_encode(['filename'=>$name,'data'=>base64_encode($bytes)]));
        $import=app(\App\Services\CodeChecking\LegacyImport::class);$map=['tasks'=>['12'=>12]];
        $report=$root.'/preflight.jsonl';$summary=$import->run($root,$map,false,null,'UTC',false,$report);
        $this->assertSame([],$summary['issues']);$this->assertGreaterThan(0,$summary['warnings']);
        $this->assertStringContainsString('renamed_execution_path',file_get_contents($report));
        $this->assertSame([],Storage::disk('code_originals')->allFiles());
        $import->run($root,$map,true,4);$record=CodeSubmission::firstOrFail();
        $this->assertSame($name,$record->files[0]['original_name']);$this->assertLessThanOrEqual(240,strlen($record->files[0]['name']));
        $this->assertTrue($record->teacher_viewed);$this->assertTrue($record->is_public);
        $row=DB::table('code_artifacts')->find($record->files[0]['artifact']['blob_id']);
        $this->assertSame('code_originals',$row->storage_disk);$this->assertNotNull($row->verified_at);
        $this->assertSame($bytes,Storage::disk('code_originals')->get($row->storage_key));
        $this->rewriteLegacy($root,['id'=>'MISSING','available_without_auth'=>false],json_encode(['filename'=>'missing.png']));
        $import->run($root,$map,true,4);
        $bad=CodeSubmission::where('legacy_id','MISSING')->firstOrFail();
        $this->assertTrue($bad->source_metadata['requires_conversion']);
        $this->assertContains('invalid_original',$bad->source_metadata['migration_warnings']);
        $this->assertSame('legacy-source.json',$bad->files[0]['name']);$this->assertGreaterThan(0,$bad->files[0]['artifact']['bytes']);
    }

    public function test_preflight_validates_links_checksums_and_every_matching_old_url_is_relinked(): void
    {
        $root=$this->legacyExport();$import=app(\App\Services\CodeChecking\LegacyImport::class);$map=['tasks'=>['12'=>12]];
        file_put_contents($root.'/similarities.jsonl',json_encode(['code_id'=>'LEGACY','code_id2'=>'MISSING','percent'=>101])."\n");
        $this->rewriteLegacy($root);
        $this->assertCount(2,$import->run($root,$map)['issues']);
        $this->assertSame(0,CodeSubmission::count());
        file_put_contents($root.'/similarities.jsonl','');$this->rewriteLegacy($root);
        DB::unprepared("CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,submitted TEXT,updated_at TEXT,geekpaste_integrity_dismissed_at TEXT);
            INSERT INTO solutions VALUES (1,2,7,12,'https://paste.test/?id=LEGACY',4,'2020-01-01','2020-01-02',NULL),(2,2,7,12,'https://paste.test/?id=LEGACY',5,'2020-01-01','2020-01-03',NULL);");
        $import->run($root,$map,true,4);
        $this->assertSame(2,DB::table('solutions')->where('text',url('/insider/code-submissions/'.CodeSubmission::first()->id))->count());
        $this->assertSame([4,5],DB::table('solutions')->orderBy('id')->pluck('mark')->all());
        $this->assertSame(['2020-01-02','2020-01-03'],DB::table('solutions')->orderBy('id')->pluck('updated_at')->all());
    }

    public function test_historical_reports_parse_only_saved_public_fields_and_never_enable_purchases(): void
    {
        $parser=app(\App\Services\CodeChecking\HistoricalReport::class);
        $text="Пройдено тестов: 1 из 2.\n\nТест 1 (публичный): пройден.\n\nВвод:\n<pre>&lt;x&gt;</pre>\n\nВывод программы:\n<pre>Тест 999 (приватный): пройден.</pre>\n\nТест 2 (приватный): неверный ответ.";
        $report=$parser->parse($text);
        $this->assertSame('<x>',$report['tests'][0]['input']['text']);
        $this->assertSame('Тест 999 (приватный): пройден.',$report['tests'][0]['actual']['text']);
        $this->assertFalse($report['tests'][1]['can_purchase']);$this->assertArrayNotHasKey('input',$report['tests'][1]);
        $this->assertNull($parser->parse(str_replace('1 из 2','2 из 2',$text)));
        $root=$this->legacyExport();$this->rewriteLegacy($root,['check_comments'=>$text]);
        app(\App\Services\CodeChecking\LegacyImport::class)->run($root,['tasks'=>['12'=>12]],true,4);
        $this->login()->getJson('/insider/code-submissions/'.CodeSubmission::first()->id)->assertJsonPath('report.legacy',true)->assertJsonPath('report.tests.1.can_purchase',false);
    }

    public function test_original_storage_is_separate_from_text_and_replacement_retains_the_old_snapshot(): void
    {
        $base='/insider/courses/7/tasks/12';$data="%PDF-1.7\0original";
        $this->login(1)->putJson($base.'/checker/private',['language'=>'python','check_type'=>'gpt','private_instructions'=>'Secret'])->assertOk();
        $first=$this->post($base.'/checker/files',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('учебник.pdf',$data),'path'=>'book.pdf','kind'=>'attachment','is_public'=>'1'],['Accept'=>'application/json'])->assertCreated()->json();
        $record=\App\CodeTaskFile::find($first['id']);$sha=$record->artifact['blob_id'];
        $this->assertSame(0,DB::table('code_artifact_contents')->where('id',$sha)->count());
        $this->login()->get($first['download_url'])->assertStreamedContent($data);
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(1)'])->assertCreated();
        $submission=CodeSubmission::firstOrFail();
        $replacement=$this->login(1)->post($base.'/checker/files/'.$first['id'].'/replace',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('new.pdf','new version'),'path'=>'book.pdf','kind'=>'attachment','is_public'=>'1'],['Accept'=>'application/json'])->assertCreated()->json();
        $this->assertSame($first['id'],$replacement['previous_id']);
        $this->assertSoftDeleted('code_task_files',['id'=>$first['id']]);
        $this->login()->get('/insider/code-submissions/'.$submission->id.'/task-files/0')->assertStreamedContent($data);
        $this->get($first['download_url'])->assertNotFound();
        $codeSha=$submission->files[0]['artifact']['blob_id'];$this->assertSame('print(1)',DB::table('code_artifact_contents')->where('id',$codeSha)->value('content'));
    }

    public function test_private_public_flags_comparison_and_manual_override_have_scoped_access(): void
    {
        $one=$this->createSubmission();$two=$this->createSubmission();$id=$one['id'];
        CodeExecutionRun::find($one['execution_id'])->update(['state'=>'completed','points'=>4,'finished_at'=>now(),'result_hash'=>str_repeat('a',64)]);
        $this->login()->get('/shared/code-submissions/'.$id)->assertNotFound();
        $this->login(3)->putJson('/insider/code-submissions/'.$id.'/flags',['is_public'=>true])->assertForbidden();
        $this->login()->putJson('/insider/code-submissions/'.$id.'/flags',['teacher_viewed'=>true])->assertForbidden();
        $this->putJson('/insider/code-submissions/'.$id.'/flags',['is_public'=>true])->assertOk();
        $this->get('/shared/code-submissions/'.$id)->assertOk()->assertSee('print(2+3)')->assertDontSee('PRIVATE RUBRIC');
        $this->getJson('/insider/code-submissions/'.$id.'/compare?with='.$two['id'])->assertOk()->assertJsonPath('before.id',$two['id']);
        $this->login(3)->getJson('/insider/code-submissions/'.$id.'/compare?with='.$two['id'])->assertForbidden();
        $this->login(1)->putJson('/insider/code-submissions/'.$id.'/flags',['teacher_viewed'=>true])->assertOk();
        $this->getJson('/insider/courses/7/tasks/12/code-submissions?teacher_viewed=0')->assertJsonCount(1,'items');
        $this->login()->postJson('/insider/code-submissions/'.$id.'/manual-review')->assertForbidden();
        $this->login(1)->postJson('/insider/code-submissions/'.$id.'/manual-review')->assertCreated();
        $new=CodeSubmission::find($id)->currentRun;
        $this->assertSame('awaiting_review',$new->state);$this->assertSame($one['execution_id'],$new->snapshot['manual_override_of']);
        $this->assertSame(4,CodeExecutionRun::find($one['execution_id'])->points);
        $this->assertSame(str_repeat('a',64),CodeExecutionRun::find($one['execution_id'])->result_hash);
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        $this->putJson('/insider/code-submissions/'.$id.'/grade',['points'=>7,'comment'=>'Teacher override'])->assertOk();
        $this->assertSame(7,$new->fresh()->points);
        $this->login()->putJson('/insider/code-submissions/'.$id.'/flags',['is_public'=>false])->assertOk();
        $this->get('/shared/code-submissions/'.$id)->assertNotFound();
    }

    public function test_private_s3_adapter_round_trip_and_signed_links(): void
    {
        if(!getenv('CODE_S3_TEST_ENDPOINT'))$this->markTestSkipped('Set CODE_S3_TEST_ENDPOINT to an isolated S3-compatible server.');
        Storage::forgetDisk('code_originals');
        config(['filesystems.disks.code_originals'=>['driver'=>'s3','key'=>'test','secret'=>'test','region'=>'us-east-1','bucket'=>'cp-test-originals','endpoint'=>getenv('CODE_S3_TEST_ENDPOINT'),'use_path_style_endpoint'=>true,'throw'=>true]]);
        $disk=Storage::disk('code_originals');try{$disk->getClient()->createBucket(['Bucket'=>'cp-test-originals']);}catch(\Throwable $e){}
        $value="original\0binary\xff";$artifact=app(Artifacts::class)->putOriginal($value);
        $row=DB::table('code_artifacts')->find($artifact['blob_id']);
        $this->assertSame($value,$disk->get($row->storage_key));
        $this->assertSame($value,app(Artifacts::class)->get($artifact['blob_id']));
        $response=app(Artifacts::class)->download($artifact,'оригинал.bin');
        $this->assertSame(302,$response->getStatusCode());
        $url=$response->headers->get('Location');$this->assertStringContainsString('X-Amz-Signature=',$url);
        $this->assertStringContainsString('X-Amz-Expires=300',$url);
        $this->assertSame($value,file_get_contents($url));
        $this->assertSame('private',$disk->getVisibility($row->storage_key));
        Storage::forgetDisk('code_originals');
        $this->assertSame($value,Storage::disk('code_originals')->get($row->storage_key));
        $this->assertSame($value,app(Artifacts::class)->withLocalFile($artifact,fn($path)=>file_get_contents($path)));
        Storage::disk('code_originals')->delete($row->storage_key);
    }

    public function test_native_review_request_creates_only_an_ungraded_queue_entry_and_notifies_once(): void
    {
        DB::statement('ALTER TABLE course_teachers ADD COLUMN hidden_from_stats BOOLEAN DEFAULT 0');
        DB::unprepared('CREATE TABLE solutions (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,course_id INTEGER,task_id INTEGER,teacher_id INTEGER,text TEXT,mark INTEGER,raw_mark INTEGER,comment TEXT,submitted TEXT,checked TEXT,recheck_requested BOOLEAN DEFAULT 0,recheck_comment TEXT,review_skipped BOOLEAN DEFAULT 0,geekpaste_integrity_dismissed_at TEXT,created_at TEXT,updated_at TEXT);');
        $created=$this->createSubmission();$id=$created['id'];
        CodeExecutionRun::find($created['execution_id'])->update(['state'=>'completed','points'=>4,'finished_at'=>now()]);
        $url='/insider/code-submissions/'.$id.'/review-request';
        $this->login(3)->postJson($url,['comment'=>'Check this work please'])->assertForbidden();
        $this->login()->postJson($url,['comment'=>' '])->assertUnprocessable();
        $this->postJson($url,['comment'=>'Не согласен со вторым критерием'])->assertOk()->assertJsonPath('requested',true);
        $this->postJson($url,['comment'=>'Повторный запрос не должен дублироваться'])->assertOk()->assertJsonPath('comment','Не согласен со вторым критерием');
        $this->assertDatabaseCount('solutions',1);
        $solution=\App\Solution::firstOrFail();$this->assertNull($solution->mark);$this->assertNull($solution->raw_mark);$this->assertNull($solution->checked);
        Notification::assertSentToTimes(User::find(1),\App\Notifications\NewSolution::class,1);
        $this->assertDatabaseCount('coin_transactions',1);
    }

    public function test_storage_preparation_is_dry_repeatable_and_keeps_the_old_bytes(): void
    {
        $text="old text\0with nul";$sha=hash('sha256',$text);$artifacts=app(Artifacts::class);
        DB::table('code_artifacts')->insert(['id'=>$sha,'bytes'=>strlen($text),'created_at'=>now()]);
        Storage::disk('code_checking')->put($artifacts->path($sha),$text);
        $this->artisan('code-checking:prepare-storage')->assertSuccessful();
        $this->assertDatabaseCount('code_artifact_contents',0);
        $this->artisan('code-checking:prepare-storage',['--apply'=>true,'--actor'=>2])->assertFailed();
        $this->artisan('code-checking:prepare-storage',['--apply'=>true,'--actor'=>4])->assertSuccessful();
        $this->artisan('code-checking:prepare-storage',['--apply'=>true,'--actor'=>4])->assertSuccessful();
        $this->assertSame('base64',DB::table('code_artifact_contents')->where('id',$sha)->value('encoding'));
        $this->assertSame($text,$artifacts->get($sha));$this->assertTrue(Storage::disk('code_checking')->exists($artifacts->path($sha)));
    }

    public function test_export_sidecars_are_verified_preserved_and_admin_only(): void
    {
        $root=$this->legacyExport();$bytes="encrypted service state\0original";
        file_put_contents($root.'/service-state.json',$bytes);
        $manifest=json_decode(file_get_contents($root.'/manifest.json'),true);
        $manifest['schema_version']=2;$manifest['files']['service-state.json']=hash('sha256',$bytes);
        file_put_contents($root.'/manifest.json',json_encode($manifest));
        $id=hash_file('sha256',$root.'/manifest.json');
        app(\App\Services\CodeChecking\LegacyImport::class)->run($root,['tasks'=>['12'=>12]],true,4);
        $this->assertDatabaseCount('code_legacy_exports',1);
        $url='/insider/settings/code-executor/legacy-exports/'.$id.'/files/service-state.json';
        $this->login()->get($url)->assertForbidden();$this->login(1)->get($url)->assertForbidden();
        $this->login(4)->get($url)->assertStreamedContent($bytes);
        file_put_contents($root.'/service-state.json','corrupted');
        try {app(\App\Services\CodeChecking\LegacyImport::class)->run($root,['tasks'=>['12'=>12]]);$this->fail('Checksum must fail');}
        catch(\InvalidArgumentException $e){$this->assertStringContainsString('checksum mismatch',$e->getMessage());}
    }

    public function test_changed_source_retains_original_run_files_and_native_review_prevents_refresh(): void
    {
        $root=$this->legacyExport();$import=app(\App\Services\CodeChecking\LegacyImport::class);$map=['tasks'=>['12'=>12]];
        $import->run($root,$map,true,4);$record=CodeSubmission::firstOrFail();$old=$record->current_run_id;
        $this->rewriteLegacy($root,[],"print('updated')\n");
        $import->run($root,$map,true,4,'UTC',true);
        $this->login()->get('/insider/code-submissions/'.$record->id.'/runs/'.$old.'/files/0')->assertStreamedContent("  print(6)\n");
        $this->get('/legacy/geekpaste/LEGACY/raw')->assertRedirect('/insider/code-submissions/'.$record->id.'/legacy-source');
        $this->login(1)->postJson('/insider/code-submissions/'.$record->id.'/manual-review')->assertCreated();
        $this->rewriteLegacy($root,['check_points'=>8]);
        $summary=$import->run($root,$map,false,null,'UTC',true);
        $this->assertStringContainsString('native_review_must_not_be_overwritten',implode(' ',$summary['issues']));
    }

    public function test_notebook_repository_workbook_and_original_zip_keep_available_bytes(): void
    {
        $root=$this->legacyExport();$map=['tasks'=>['12'=>12]];$import=app(\App\Services\CodeChecking\LegacyImport::class);
        $notebook=json_encode(['nbformat'=>4,'cells'=>[['cell_type'=>'code','source'=>["  print(1)\n"]]]]);
        $this->rewriteLegacy($root,['id'=>'NOTEBOOK','lang'=>'ipynb'],$notebook);$import->run($root,$map,true,4);
        $note=CodeSubmission::where('legacy_id','NOTEBOOK')->firstOrFail();
        $this->assertSame('python',$note->language);$this->assertSame($notebook,app(Artifacts::class)->get($note->files[0]['artifact']['blob_id']));
        $this->rewriteLegacy($root,['id'=>'GITHUB','lang'=>'github'],json_encode(['repo_url'=>'https://github.com/old/repository','ref'=>'main','files'=>[
            ['name'=>'main.py','content'=>'print(1)','is-binary'=>false],['name'=>'data.png','content'=>'Файл размером 512 байт.','is-binary'=>true]]]));
        $import->run($root,$map,true,4);$repo=CodeSubmission::where('legacy_id','GITHUB')->firstOrFail();
        $this->assertTrue($repo->source_metadata['commit_unknown']);$this->assertTrue($repo->source_metadata['requires_conversion']);
        $this->assertCount(1,$repo->files);$this->assertContains('missing_binary_file',$repo->source_metadata['migration_warnings']);
        $workbook="PK\0original workbook";
        $this->rewriteLegacy($root,['id'=>'WORKBOOK','lang'=>'xlsx'],json_encode(['filename'=>'report.xlsx','data'=>base64_encode($workbook)]));
        $import->run($root,$map,true,4);$sheet=CodeSubmission::where('legacy_id','WORKBOOK')->firstOrFail();
        $this->login()->get('/insider/code-submissions/'.$sheet->id.'/files/0')->assertStreamedContent($workbook);
        $archive=$root.'/original.zip';$zip=new \ZipArchive();$zip->open($archive,\ZipArchive::CREATE);$zip->addFromString('main.py','print(1)');$zip->addFromString('data.bin',"\xff\0original");$zip->close();
        $sha=hash_file('sha256',$archive);copy($archive,$root.'/blobs/'.$sha);
        $this->rewriteLegacy($root,['id'=>'ARCHIVE','lang'=>'zip','original_archive'=>['sha256'=>$sha,'bytes'=>filesize($archive),'path'=>'blobs/'.$sha]],'[]');
        $import->run($root,$map,true,4);$saved=CodeSubmission::where('legacy_id','ARCHIVE')->firstOrFail();
        $this->assertFalse($saved->source_metadata['requires_conversion']);
        $this->get('/insider/code-submissions/'.$saved->id.'/files/0')->assertStreamedContent(file_get_contents($archive));
        $this->assertDatabaseCount('coin_transactions',1);Queue::assertNothingPushed();
    }

    public function test_teacher_group_limits_and_overrides_roundtrip_without_becoming_student_settings(): void
    {
        $body=$this->configBody();$body['test_config']+=['scoring'=>'groups','comparison'=>'float','memory_limit'=>256,
            'process_limit'=>64,'output_limit'=>65536,'disable_swap'=>true,'absolute_tolerance'=>'0.00001',
            'groups'=>[['id'=>'basic','name'=>'PRIVATE BASIC','points'=>4],['id'=>'edge','name'=>'PRIVATE EDGE','points'=>6]]];
        $body['test_config']['tests'][0]+=['group'=>'basic','time_limit'=>7,'comparison'=>'exact','output_limit'=>2048];
        $body['test_config']['tests'][]=['input'=>'1 2','output'=>'3','is_public'=>false,'group'=>'edge'];
        $url='/insider/courses/7/tasks/12/checker';
        $this->login(1)->putJson($url.'/private',$body)->assertOk()->assertJsonPath('config.test_config.groups.1.points',6)
            ->assertJsonPath('config.test_config.memory_limit',256)->assertJsonPath('config.test_config.tests.0.time_limit',7)
            ->assertJsonPath('config.test_config.absolute_tolerance','0.00001');
        $revision=CodeTaskChecker::find(12)->revision;
        $invalid=$body;$invalid['test_config']['groups'][1]['points']=7;
        $this->putJson($url.'/private',$invalid)->assertUnprocessable();
        $this->assertSame($revision,CodeTaskChecker::find(12)->revision);
        $this->login()->getJson($url)->assertOk()->assertDontSee('PRIVATE BASIC')->assertDontSee('PRIVATE EDGE')->assertDontSee('absolute_tolerance');
        $created=$this->postJson('/insider/courses/7/tasks/12/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(3)'])->assertCreated()->json();
        $snapshot=CodeExecutionRun::find($created['execution_id'])->snapshot['test_config'];
        $this->assertSame(6,$snapshot['groups'][1]['points']);$this->assertTrue($snapshot['disable_swap']);
        $this->assertSame('exact',$snapshot['tests'][0]['comparison']);
    }

}
