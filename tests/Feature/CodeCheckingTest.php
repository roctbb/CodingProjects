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
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, state TEXT DEFAULT 'started');
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, text TEXT, max_mark INTEGER, is_code BOOLEAN, is_hidden BOOLEAN, created_at TEXT, updated_at TEXT);
            CREATE TABLE blocked_tasks (task_id INTEGER, course_id INTEGER, user_id INTEGER);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT);
            INSERT INTO users VALUES (1, 'Teacher', 'teacher@test', 'teacher', '2026-01-01'), (2, 'Student', 'student@test', 'student', '2026-01-01'), (3, 'Other', 'other@test', 'student', '2026-01-01'), (4, 'Admin', 'admin@test', 'admin', '2026-01-01');
            INSERT INTO courses (id,program_id) VALUES (7, 1), (8, 2);
            INSERT INTO course_teachers (course_id,user_id) VALUES (7, 1);
            INSERT INTO course_students (course_id,user_id) VALUES (7, 2), (7, 3);
            INSERT INTO program_steps VALUES (1, 1, 1);
            INSERT INTO lessons VALUES (1,1);
            INSERT INTO lesson_info VALUES (1,7,1,'2000-01-01');
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

    public function test_test_checkers_still_reject_null_or_invalid_test_configuration(): void
    {
        $url='/insider/courses/7/tasks/12/checker/private';
        foreach ([null,'invalid'] as $tests) {
            $this->login(1)->putJson($url,array_replace($this->configBody(),['test_config'=>$tests]))
                ->assertUnprocessable()->assertJsonValidationErrors('test_config');
        }
        $this->putJson($url,array_replace($this->configBody(),['test_config'=>['tests'=>[]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('tests');
        $this->assertDatabaseCount('code_task_checkers',0);
    }

    public function test_teacher_uses_own_draft_and_the_normal_submission_limits_and_pipeline():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',['language'=>'python','check_type'=>'gpt','gpt_rate_limit'=>1])->assertOk();
        $this->getJson($base.'/checker')->assertJsonPath('teacher_submission_allowed',true)->assertJsonPath('viewer_user_id',1);
        $this->getJson($base.'/code-draft')->assertOk();
        $this->putJson($base.'/code-draft',['revision'=>0,'mode'=>'code','editor_files'=>[['path'=>'main.py','content'=>'print(5)']]])->assertOk();
        DB::table('blocked_tasks')->insert(['task_id'=>12,'course_id'=>7,'user_id'=>1]);
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>1])->assertForbidden();
        DB::table('blocked_tasks')->delete();Queue::fake();
        $first=$this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>1])->assertCreated()->json();
        Queue::assertPushed(\App\Jobs\CheckCodeIntegrity::class);Queue::assertPushed(\App\Jobs\DispatchCodeExecution::class);
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(6)'])->assertStatus(429);
        $this->getJson($base.'/code-submissions?user_id=1')->assertJsonCount(1,'items');
        $this->postJson($base.'/code-submissions/recheck-latest',['latest_configuration'=>true])->assertJsonPath('execution_ids',[])->assertJsonPath('skipped_active',[]);
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        $data=['execution_id'=>$first['execution_id'],'state'=>'done','points'=>10,'comment'=>'Teacher feedback'];
        $hash=hash('sha256',json_encode($data));
        app(\App\Services\CodeChecking\Results::class)->accept(CodeExecutionRun::findOrFail($first['execution_id']),$data,$hash);
        app(\App\Services\CodeChecking\Results::class)->accept(CodeExecutionRun::findOrFail($first['execution_id']),$data,$hash);
        $this->login()->getJson($base.'/code-submissions')->assertJsonCount(0,'items');
    }

    public function test_history_retains_each_old_cp_attempt_without_borrowing_latest_gp_result():void
    {
        $base='/insider/courses/7/tasks/12';
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,raw_mark INTEGER,evaluated_mark INTEGER,comment TEXT,submitted TEXT,checked TEXT,created_at TEXT,updated_at TEXT,geekpaste_code_id TEXT)');
        $this->login(1)->putJson($base.'/checker/private',array_replace($this->configBody(),['check_type'=>'gpt']))->assertOk();
        $source=$this->login()->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(10)'])->assertCreated()->json();
        $record=CodeSubmission::findOrFail($source['id']);
        $record->update(['legacy_id'=>'OLD123','source_metadata'=>['legacy_id'=>'OLD123'],'solution_id'=>101]);
        CodeExecutionRun::findOrFail($source['execution_id'])->update(['state'=>'completed','points'=>10,'comment'=>'LATEST GP RESULT','finished_at'=>now()]);
        foreach([[101,2,5,'Earlier feedback','2026-11-10 12:00:00'],[102,2,10,'Later feedback','2026-11-11 12:00:00'],[201,3,2,'Other student','2026-11-12 12:00:00']] as [$id,$user,$mark,$comment,$date])
            DB::table('solutions')->insert(['id'=>$id,'user_id'=>$user,'course_id'=>7,'task_id'=>12,'text'=>url('/insider/code-submissions/'.$record->id),
                'mark'=>$mark,'raw_mark'=>$mark,'comment'=>$comment,'submitted'=>$date,'checked'=>$date,'created_at'=>$date,'updated_at'=>$date,'geekpaste_code_id'=>'OLD123']);
        $list=$this->login(1)->getJson($base.'/code-submissions?user_id=2')->assertOk()->assertJsonCount(2,'items')->assertJsonPath('total_attempts',2)->json();
        $this->assertSame(['solution-102','solution-101'],array_column($list['items'],'id'));
        $this->assertSame([10,5],array_column($list['items'],'awarded_xp'));
        $this->assertSame(['legacy_attempt','legacy_attempt'],array_column($list['items'],'kind'));
        $this->assertSame([true,false],array_column($list['items'],'is_best'));
        $this->assertSame('solution-102',$list['best_attempt']['id']);
        // Automatic grade timestamps are not evidence that a teacher viewed the work.
        $this->getJson($base.'/code-submissions?user_id=2&teacher_viewed=0')->assertJsonCount(2,'items');
        $this->assertArrayNotHasKey('report',$list['items'][0]);$this->assertArrayNotHasKey('files',$list['items'][0]);
        $this->getJson($base.'/code-attempts/101')->assertOk()->assertJsonPath('awarded_xp',5)->assertJsonPath('comment','Earlier feedback')
            ->assertJsonPath('report',null)->assertJsonPath('source_version_unknown',true)->assertJsonPath('source_submission_id',$record->id)->assertDontSee('LATEST GP RESULT')
            ->assertJsonPath('can_grade',true)->assertJsonPath('review_mark',5)->assertJsonPath('review_comment','Earlier feedback')
            ->assertJsonPath('check_type','gpt')->assertJsonPath('comment_available',true)
            ->assertJsonPath('review_url',url('/insider/courses/7/steps/1').'?student=2&task=12&attempt=solution-101#task12');
        $this->getJson('/insider/code-submissions/'.$record->id)->assertOk()->assertJsonPath('points',10)->assertJsonPath('awarded_xp',null);
        $this->putJson('/insider/code-submissions/'.$record->id.'/flags',['teacher_viewed'=>true])->assertOk();
        $this->getJson($base.'/code-submissions?user_id=2&teacher_viewed=0')->assertJsonCount(0,'items');
        $this->login()->getJson($base.'/code-attempts/101')->assertOk();
        $this->login(3)->getJson($base.'/code-attempts/101')->assertForbidden();
        $this->getJson($base.'/code-attempts/201')->assertOk()->assertJsonPath('source_submission_id',null)->assertJsonPath('source_url',null);
        $this->getJson($base.'/code-submissions?user_id=2')->assertForbidden();
    }

    public function test_legacy_attempt_test_comments_are_metadata_only_and_ai_or_manual_feedback_remains_available():void
    {
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,raw_mark INTEGER,comment TEXT,submitted TEXT,checked TEXT)');
        $created=$this->createSubmission();$submission=CodeSubmission::findOrFail($created['id']);
        $submission->update(['legacy_id'=>'OLDTEST','source_metadata'=>['legacy_id'=>'OLDTEST'],'solution_id'=>501]);
        $raw="Пройдено тестов: 0 из 1.\n\nТест 1 (публичный): неверный ответ.\n\nВвод:\n<pre>SECRET HISTORICAL INPUT</pre>\n\nВывод программы:\n<pre>SECRET HISTORICAL OUTPUT</pre>";
        DB::table('solutions')->insert(['id'=>501,'user_id'=>2,'course_id'=>7,'task_id'=>12,'text'=>url('/insider/code-submissions/'.$submission->id),'mark'=>5,'raw_mark'=>5,'comment'=>$raw,'submitted'=>'2026-11-10 12:00:00']);
        $base='/insider/courses/7/tasks/12/code-attempts/';
        foreach([1,2] as $viewer)$this->login($viewer)->getJson($base.'501')->assertOk()->assertJsonPath('check_type','tests')->assertJsonPath('awarded_xp',5)
            ->assertJsonPath('comment_available',true)->assertJsonPath('comment','')->assertJsonPath('review_comment','')->assertDontSee('SECRET HISTORICAL')->assertDontSee('<pre>');
        $this->assertSame($raw,DB::table('solutions')->where('id',501)->value('comment'));
        DB::table('solutions')->where('id',501)->update(['comment'=>'UNSTRUCTURED TEST SECRET <pre>saved output</pre>']);
        $this->getJson($base.'501')->assertJsonPath('check_type','tests')->assertJsonPath('comment','')->assertJsonPath('review_comment','')->assertDontSee('UNSTRUCTURED TEST SECRET');
        foreach(['gpt'=>'AI feedback','manual'=>'Teacher feedback'] as $type=>$feedback){
            CodeTaskChecker::whereKey(12)->update(['config'=>array_replace(CodeTaskChecker::find(12)->config,['check_type'=>$type])]);
            DB::table('solutions')->where('id',501)->update(['text'=>'Historical answer','comment'=>$feedback]);
            $submission->update(['solution_id'=>null]);
            $this->login(1)->getJson($base.'501')->assertOk()->assertJsonPath('check_type',$type)->assertJsonPath('comment_available',true)
                ->assertJsonPath('comment',$feedback)->assertJsonPath('review_comment',$feedback);
        }
        // Even without a linked source and with today's manual configuration,
        // an identifiable old test report stays private until a test is opened.
        DB::table('solutions')->where('id',501)->update(['comment'=>$raw]);
        $this->getJson($base.'501')->assertJsonPath('check_type','tests')->assertJsonPath('comment','')->assertJsonPath('review_comment','')->assertDontSee('SECRET HISTORICAL');
        DB::table('solutions')->where('id',501)->update(['comment'=>'']);
        $this->getJson($base.'501')->assertJsonPath('comment_available',false);
    }

    public function test_course_author_access_does_not_depend_on_the_global_teacher_role():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',$this->configBody())->assertOk();
        DB::table('users')->where('id',3)->update(['role'=>'teacher']);
        $this->login(3)->putJson($base.'/code-draft',['revision'=>0,'mode'=>'code','editor_files'=>[['path'=>'main.py','content'=>'print(5)']]])->assertOk();
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>1])->assertCreated()->assertJsonPath('user_id',3);
        DB::table('users')->insert(['id'=>5,'name'=>'Unrelated teacher','email'=>'unrelated@test','role'=>'teacher','email_verified_at'=>'2026-01-01']);
        $this->login(5)->getJson($base.'/code-draft')->assertForbidden();
        $this->putJson($base.'/code-draft',['revision'=>0,'mode'=>'code','editor_files'=>[['path'=>'main.py','content'=>'print(5)']]])->assertForbidden();
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(5)'])->assertForbidden();
    }

    public function test_private_submission_html_links_redirect_to_the_task_but_json_and_shared_links_keep_their_contract():void
    {
        $source=$this->createSubmission();$url='/insider/code-submissions/'.$source['id'];
        $canonical=url('/insider/courses/7/steps/1').'?student=2&task=12&attempt='.$source['id'].'#task12';
        $this->login()->get($url)->assertRedirect($canonical);
        $this->getJson($url)->assertOk()->assertJsonPath('id',$source['id']);
        $this->login(1)->get($url)->assertRedirect($canonical);
        $this->login(3)->get($url)->assertForbidden();
    }

    public function test_task_deep_links_bind_attempts_to_their_actual_author_course_and_visible_task():void
    {
        $source=$this->createSubmission();$location=app(\App\Services\TaskLocation::class);
        $course=\App\Course::findOrFail(7);$tasks=collect([\App\Task::findOrFail(12)]);$teacher=User::findOrFail(1);
        $context=$location->context(new \Illuminate\Http\Request(['student'=>2,'attempt'=>$source['id']]),$teacher,$course,$tasks);
        $this->assertSame(['student_id'=>2,'task_id'=>12,'attempt'=>$source['id']],$context);
        foreach([
            [new \Illuminate\Http\Request(['student'=>3,'attempt'=>$source['id']]),$teacher,$course,$tasks],
            [new \Illuminate\Http\Request(['student'=>2,'task'=>13,'attempt'=>$source['id']]),$teacher,$course,$tasks],
            [new \Illuminate\Http\Request(['student'=>2,'attempt'=>$source['id']]),$teacher,$course,collect()],
            [new \Illuminate\Http\Request(['student'=>2,'attempt'=>$source['id']]),User::find(4),\App\Course::find(8),$tasks],
        ] as [$request,$actor,$selectedCourse,$selectedTasks]){
            try{$location->context($request,$actor,$selectedCourse,$selectedTasks);$this->fail('A mismatched deep link was accepted.');}
            catch(\Illuminate\Database\Eloquent\ModelNotFoundException $error){$this->assertTrue(true);}
            catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame(404,$error->getStatusCode());}
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $location->context(new \Illuminate\Http\Request(['student'=>3,'attempt'=>$source['id']]),User::find(2),$course,$tasks);
    }

    public function test_legacy_student_review_links_redirect_to_the_selected_attempt_on_the_task():void
    {
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,submitted TEXT)');
        foreach([[101,'2026-11-10 12:00:00'],[102,'2026-11-11 12:00:00']] as [$id,$date])DB::table('solutions')->insert(['id'=>$id,'user_id'=>2,'course_id'=>7,'task_id'=>12,'text'=>'historical answer','mark'=>5,'submitted'=>$date]);
        $this->login(1)->get('/insider/courses/7/tasks/12/student/2')->assertRedirect(url('/insider/courses/7/steps/1').'?student=2&task=12&attempt=solution-102#task12');
        $this->get('/insider/courses/7/tasks/12/student/2?legacy_attempt=101')->assertRedirect(url('/insider/courses/7/steps/1').'?student=2&task=12&attempt=solution-101#task12');
        $this->get('/insider/courses/7/tasks/12/student/2?legacy_attempt=999')->assertNotFound();
        $this->login(3)->get('/insider/courses/7/tasks/12/student/2')->assertForbidden();
    }

    public function test_best_attempt_remains_available_when_its_history_page_or_review_filter_is_not_visible():void
    {
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,submitted TEXT)');
        for($id=1;$id<=22;$id++)DB::table('solutions')->insert(['id'=>$id,'user_id'=>2,'course_id'=>7,'task_id'=>12,'text'=>'saved answer','mark'=>$id<=2?10:5,'submitted'=>sprintf('2026-11-%02d 12:00:00',$id)]);
        $response=$this->login(1)->getJson('/insider/courses/7/tasks/12/code-submissions?user_id=2')->assertJsonCount(20,'items')->assertJsonPath('best_attempt.id','solution-2')->assertJsonPath('has_more',true)->json();
        $this->assertSame([],array_filter($response['items'],fn($item)=>$item['is_best']));
        $this->getJson('/insider/courses/7/tasks/12/code-submissions?user_id=2&page=2')->assertJsonPath('items.0.id','solution-2')->assertJsonPath('items.0.is_best',true);
        $this->getJson('/insider/courses/7/tasks/12/code-submissions?user_id=2&teacher_viewed=1')->assertJsonCount(0,'items')->assertJsonPath('best_attempt.id','solution-2');
    }

    public function test_history_summary_query_count_does_not_grow_with_attempt_count_or_load_private_payloads():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',$this->configBody())->assertOk();
        $make=fn()=>$this->login()->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'PRIVATE SOURCE'])->assertCreated();
        $make();
        DB::enableQueryLog();DB::flushQueryLog();
        $this->getJson($base.'/code-submissions')->assertJsonCount(1,'items');$one=count(DB::getQueryLog());DB::disableQueryLog();
        for($i=0;$i<19;$i++)$make();
        DB::enableQueryLog();DB::flushQueryLog();
        $result=$this->getJson($base.'/code-submissions')->assertOk()->assertJsonCount(20,'items')->assertDontSee('PRIVATE SOURCE')->assertDontSee('PRIVATE RUBRIC')->json();
        $many=count(DB::getQueryLog());DB::disableQueryLog();
        $this->assertLessThanOrEqual($one+1,$many);
        foreach(['report','files','comment','integrity','subscription','runs'] as $field)$this->assertArrayNotHasKey($field,$result['items'][0]);
    }

    public function test_teacher_progress_includes_live_and_failed_student_attempts_but_excludes_staff_and_gp_archives():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',$this->configBody())->assertOk();
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'teacher own solution'])->assertCreated();
        DB::table('course_students')->insert(['course_id'=>7,'user_id'=>1]);
        $failed=$this->login()->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'student attempt'])->assertCreated()->json();
        CodeExecutionRun::findOrFail($failed['execution_id'])->update(['state'=>'error','finished_at'=>now()]);
        $archive=$this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'archival snippet'])->assertCreated()->json();
        CodeSubmission::findOrFail($archive['id'])->update(['legacy_id'=>'OLD456']);
        // A globally marked teacher can still be a learner in a different course.
        DB::table('users')->where('id',3)->update(['role'=>'teacher']);
        $this->login(3)->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'running attempt'])->assertCreated();
        $progress=app(\App\Services\CodeChecking\History::class)->ungradedProgress(7,[12])->get(12);
        $this->assertFalse($progress->has(1));
        $this->assertSame(1,$progress->get(2)['attempts']);$this->assertTrue($progress->get(2)['error']);$this->assertFalse($progress->get(2)['pending']);
        $this->assertSame(1,$progress->get(3)['attempts']);$this->assertTrue($progress->get(3)['pending']);
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

    public function test_empty_code_draft_does_not_create_a_source_file_and_cannot_be_submitted(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', $this->configBody())->assertOk();
        $this->login()->putJson($base.'/code-draft', ['revision'=>0, 'mode'=>'code', 'language'=>'python',
            'draft_revision'=>0, 'keep_files'=>[], 'editor_files'=>[]])->assertOk()->assertJsonCount(0, 'files');
        $this->getJson($base.'/code-draft')->assertJsonPath('revision', 1)->assertJsonCount(0, 'files');
        $this->postJson($base.'/code-submissions', ['request_id'=>(string) Str::uuid(), 'use_draft'=>true,
            'draft_revision'=>1])->assertUnprocessable();
        $this->assertDatabaseCount('code_submissions', 0);
        $this->putJson($base.'/code-draft', ['revision'=>1, 'mode'=>'files', 'language'=>'python',
            'draft_revision'=>1, 'keep_files'=>[], 'editor_files'=>[]])->assertOk()->assertJsonCount(0, 'files');
        $this->putJson($base.'/code-draft', ['revision'=>2, 'mode'=>'code', 'language'=>'python',
            'editor_files'=>[['path'=>'main.py', 'content'=>'print(5)']]])->assertOk()->assertJsonPath('files.0.content', 'print(5)');
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

    public function test_first_cpp_editor_save_accepts_the_ui_empty_keep_files_payload_for_students_and_teachers():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',array_replace($this->configBody(),['language'=>'cpp']))->assertOk();
        foreach([2,1] as $user){
            $this->login($user)->getJson($base.'/code-draft')->assertOk()->assertJsonPath('revision',0);
            $this->assertFalse(\App\CodeDraft::where('user_id',$user)->exists());
            $code="  #include <iostream>\nint main(){ std::cout << 5; }\n";
            $payload=['revision'=>0,'mode'=>'code','language'=>'cpp','text'=>'','draft_revision'=>0,'keep_files'=>[],
                'editor_files'=>[['path'=>'solution.cpp','content'=>$code]],'repository_url'=>'','repository_ref'=>null];
            $this->putJson($base.'/code-draft',$payload)->assertOk()->assertJsonPath('revision',1)->assertJsonPath('files.0.content',$code);
            $result=$this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>1])->assertCreated()->json();
            $this->assertSame($code,$result['files'][0]['source']);
            // Sending a solution never removes or resets its author's saved draft.
            $this->getJson($base.'/code-draft')->assertOk()->assertJsonPath('revision',1)->assertJsonPath('files.0.content',$code);
        }
        $this->assertDatabaseCount('code_drafts',2);
    }

    public function test_missing_and_multitab_drafts_return_conflicts_without_overwriting_saved_work():void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',$this->configBody())->assertOk();$this->login();
        $initial=['revision'=>0,'mode'=>'code','language'=>'python','draft_revision'=>0,'keep_files'=>[],
            'editor_files'=>[['path'=>'main.py','content'=>'print("first tab")']]];
        $this->putJson($base.'/code-draft',$initial)->assertOk()->assertJsonPath('revision',1);
        $updated=array_replace($initial,['revision'=>1,'draft_revision'=>1,'editor_files'=>[['path'=>'main.py','content'=>'print("new saved work")']]]);
        $this->putJson($base.'/code-draft',$updated)->assertOk()->assertJsonPath('revision',2);
        $this->putJson($base.'/code-draft',$initial)->assertConflict();
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>1])->assertConflict();
        $this->getJson($base.'/code-draft')->assertJsonPath('revision',2)->assertJsonPath('files.0.content','print("new saved work")');
        \App\CodeDraft::where('user_id',2)->delete();
        $this->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'use_draft'=>true,'draft_revision'=>2])->assertConflict()->assertDontSee('App\\CodeDraft');
        $this->putJson($base.'/code-draft',$updated)->assertConflict();
        $this->putJson($base.'/code-draft',array_replace($initial,['keep_files'=>[0]]))->assertConflict();
        $this->assertDatabaseCount('code_drafts',0);$this->assertDatabaseCount('code_submissions',0);
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
        $this->assertSame('2020-01-02 03:04:05', $submission->created_at->copy()->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue(app(Integrity::class)->view($submission, true)['ai']['warning']);
        $this->login()->get('/insider/code-submissions/'.$submission->id.'/legacy-source')->assertStreamedContent("  print(6)\n");
        $this->login(3)->get('/insider/code-submissions/'.$submission->id.'/legacy-source')->assertForbidden();
        $this->assertSame(1, $import->run($root, $map, true, 4)['already_imported']);
        $this->assertSame(1, CodeSubmission::count());
        Queue::assertNotPushed(\App\Jobs\DispatchCodeExecution::class);
        Queue::assertNotPushed(\App\Jobs\CheckCodeIntegrity::class);
        $this->assertFileExists($root.'/solutions.jsonl');
    }

    public static function legacySubmissionFormats(): array
    {
        return array_combine(['python','cpp','zip','ipynb','xlsx','image','github','html','js','sql'],
            array_map(fn($lang)=>[$lang],['python','cpp','zip','ipynb','xlsx','image','github','html','js','sql']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legacySubmissionFormats')]
    public function test_import_restores_the_explicit_task_format_without_widening_submission_modes(string $language): void
    {
        $root = $this->legacyExport();
        $row = json_decode(trim(file_get_contents($root.'/tasks.jsonl')), true);
        $row['lang'] = $language;
        file_put_contents($root.'/tasks.jsonl', json_encode($row)."\n");
        $this->rewriteLegacy($root);
        app(\App\Services\CodeChecking\LegacyImport::class)->run($root, ['tasks'=>['12'=>12]], true, 4);
        $expected = \App\Services\CodeChecking\LegacySubmissionFormat::settings($language);
        $config = CodeTaskChecker::findOrFail(12)->config;
        foreach ($expected as $key=>$value) $this->assertSame($value, $config[$key]);
        $this->assertSame('Public task', \App\Task::findOrFail(12)->text);
        $this->assertSame('OLD PRIVATE PROMPT', $config['private_instructions']);
        $this->assertSame('gpt', $config['check_type']);
        $base = '/insider/courses/7/tasks/12';
        $this->login()->getJson($base.'/checker')->assertOk()->assertJsonPath('submission_modes', $expected['submission_modes'])
            ->assertJsonPath('max_files', $expected['max_files'])->assertJsonPath('allowed_extensions', $expected['allowed_extensions']);
        if ($language !== 'github') {
            $this->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'mode'=>'repository',
                'repository_url'=>'https://github.com/example/project'])->assertUnprocessable();
            $mode = $expected['submission_modes'][0];
            $bad = $language === 'zip' ? 'wrong.xlsx' : 'wrong.zip';
            $this->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'mode'=>$mode,
                'editor_files'=>[['path'=>$bad, 'content'=>'not allowed']]])->assertUnprocessable();
            $this->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'mode'=>$mode,
                'editor_files'=>[['path'=>'first.'.$expected['allowed_extensions'][0], 'content'=>'one'],
                    ['path'=>'second.'.$expected['allowed_extensions'][0], 'content'=>'two']]])->assertUnprocessable();
        } else {
            $this->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'code'=>'print(5)'])->assertUnprocessable();
        }
        $this->assertSame(1, CodeSubmission::count()); // Historical source was preserved; rejected modes add nothing.
    }

    public function test_import_does_not_guess_a_missing_original_task_format(): void
    {
        $root = $this->legacyExport();
        $row = json_decode(trim(file_get_contents($root.'/tasks.jsonl')), true);
        $row['lang'] = null;
        file_put_contents($root.'/tasks.jsonl', json_encode($row)."\n");
        $this->rewriteLegacy($root);
        $import = app(\App\Services\CodeChecking\LegacyImport::class);
        $summary = $import->run($root, ['tasks'=>['12'=>12]], true, 4);
        $this->assertSame(1, $summary['needs_conversion']);
        $config = CodeTaskChecker::findOrFail(12)->config;
        $this->assertTrue($config['requires_conversion']);
        $this->assertSame([], $config['submission_modes']);
        $this->login()->postJson('/insider/courses/7/tasks/12/code-submissions',
            ['request_id'=>(string)Str::uuid(), 'code'=>'print(5)'])->assertConflict();
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

    public function test_import_dates_preserve_the_instant_after_database_round_trip_in_moscow(): void
    {
        $previous = date_default_timezone_get();
        config(['app.timezone' => 'Europe/Moscow']);
        date_default_timezone_set('Europe/Moscow');
        try {
            $root = $this->legacyExport();
            $this->rewriteLegacy($root, ['created_at' => '2020-01-02T03:04:05', 'checked_at' => '2020-01-02T06:05:06+03:00']);
            app(\App\Services\CodeChecking\LegacyImport::class)->run($root, ['tasks' => ['12' => 12]], true, 4);
            $submission = CodeSubmission::firstOrFail()->fresh();
            $run = $submission->currentRun;
            $this->assertSame('2020-01-02T03:04:05+00:00', $submission->created_at->copy()->utc()->toIso8601String());
            $this->assertSame('2020-01-02T03:05:06+00:00', $run->finished_at->copy()->utc()->toIso8601String());
            $this->assertSame('2020-01-02 06:04:05', substr(DB::table('code_submissions')->value('created_at'),0,19));
            $this->assertSame('2020-01-02 06:04:05', substr(DB::table('code_integrity_reports')->value('created_at'),0,19));

            $this->rewriteLegacy($root, ['id' => 'MOSCOW', 'created_at' => '2020-01-02T06:04:05']);
            app(\App\Services\CodeChecking\LegacyImport::class)->run($root, ['tasks' => ['12' => 12]], true, 4, 'Europe/Moscow');
            $this->assertSame('2020-01-02T03:04:05+00:00', CodeSubmission::where('legacy_id','MOSCOW')->firstOrFail()->created_at->utc()->toIso8601String());
        } finally { date_default_timezone_set($previous); }
    }

    public function test_reviewed_import_plan_is_pinned_to_source_and_public_condition(): void
    {
        $root = $this->legacyExport();
        $row = json_decode(trim(file_get_contents($root.'/tasks.jsonl')),true); ksort($row,SORT_STRING);
        $plan = ['schema_version'=>1, 'source_manifest_sha256'=>hash_file('sha256',$root.'/manifest.json'), 'tasks'=>[
            '12'=>['task_id'=>12,'source_definition_sha256'=>hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)),
                'target_public_text_sha256'=>hash('sha256','Public task'),'target_points'=>10,'action'=>'configure',
                'private_instructions'=>'Reviewed additions only','reference_answer'=>'Private reference']]];
        $import = app(\App\Services\CodeChecking\LegacyImport::class);
        $this->assertSame([], $import->run($root,['tasks'=>['12'=>12]],false,null,'UTC',false,null,$plan)['issues']);
        \App\Task::findOrFail(12)->update(['text'=>'Teacher edited the public condition']);
        $this->assertStringContainsString('stale_or_invalid_task_plan',implode(' ',$import->run($root,['tasks'=>['12'=>12]],false,null,'UTC',false,null,$plan)['issues']));
        try {$import->run($root,['tasks'=>['12'=>12]],true,4,'UTC',false,null,$plan);$this->fail('Stale plan must block all writes');}
        catch (\InvalidArgumentException $e) {$this->assertStringContainsString('preflight',$e->getMessage());}
        $this->assertDatabaseCount('code_submissions',0);
        \App\Task::findOrFail(12)->update(['text'=>'Public task']);
        $import->run($root,['tasks'=>['12'=>12]],true,4,'UTC',false,null,$plan);
        $this->assertSame('Reviewed additions only',CodeTaskChecker::findOrFail(12)->config['private_instructions']);
        $this->assertSame('Public task',\App\Task::findOrFail(12)->text);
        $this->assertSame('OLD PRIVATE PROMPT',CodeSubmission::firstOrFail()->currentRun->snapshot['private_instructions']);
    }

    public function test_reviewed_plan_can_convert_ai_to_io_and_apply_explicit_public_corrections_repeatably(): void
    {
        $root=$this->legacyExport();$row=json_decode(trim(file_get_contents($root.'/tasks.jsonl')),true);ksort($row,SORT_STRING);
        $plan=['schema_version'=>1,'source_manifest_sha256'=>hash_file('sha256',$root.'/manifest.json'),'tasks'=>[
            '12'=>['task_id'=>12,'source_definition_sha256'=>hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)),
                'target_public_text_sha256'=>hash('sha256','Public task'),'target_points'=>10,'action'=>'configure',
                'set_public_text'=>'Public task. Output one integer.','set_max_mark'=>15,'private_instructions'=>'',
                'check_type'=>'tests','test_mode'=>'io','test_config'=>['comparison'=>'tokens','scoring'=>'all_or_nothing',
                    'tests'=>[['input'=>"2 3\n",'output'=>"5\n",'is_public'=>false]]]]]];
        $import=app(\App\Services\CodeChecking\LegacyImport::class);$map=['tasks'=>['12'=>12]];
        $this->assertSame([],$import->run($root,$map,false,null,'UTC',false,null,$plan)['issues']);
        $import->run($root,$map,true,4,'UTC',false,null,$plan);
        $task=\App\Task::findOrFail(12);$config=CodeTaskChecker::findOrFail(12)->config;
        $this->assertSame(15,(int)$task->max_mark);$this->assertSame('Public task. Output one integer.',$task->text);
        $this->assertSame('tests',$config['check_type']);$this->assertFalse($config['requires_conversion']);
        $this->assertSame(['code'],$config['submission_modes']);$this->assertSame(1,$config['max_files']);$this->assertSame(['py'],$config['allowed_extensions']);
        $this->assertSame("2 3\n",app(Artifacts::class)->get($config['test_config']['tests'][0]['input']['blob_id']));
        $this->assertSame(10,CodeSubmission::firstOrFail()->currentRun->snapshot['points']);
        $this->assertSame(1,$import->run($root,$map,true,4,'UTC',false,null,$plan)['already_imported']);
        $this->assertSame([],$import->run($root,$map,false,null,'UTC',false,null,$plan)['issues']);
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

    public function test_ai_instructions_are_separate_from_the_student_condition(): void
    {
        $visible = "Сложите два числа.\nПокажите результат.";
        $private = "PRIVATE AI CRITERIA: оцените обработку отрицательных чисел.";
        $reference = 'PRIVATE REFERENCE ANSWER';
        \App\Task::findOrFail(12)->update(['text' => $visible]);
        $base = '/insider/courses/7/tasks/12';

        $this->login(1)->putJson($base.'/checker/private', [
            'language' => 'python', 'check_type' => 'gpt',
            'private_instructions' => $private, 'reference_answer' => $reference,
        ])->assertOk();
        $this->assertSame($visible, \App\Task::findOrFail(12)->text);
        $this->getJson($base.'/checker/private')->assertOk()
            ->assertJsonPath('config.private_instructions', $private)
            ->assertJsonPath('config.reference_answer', $reference);

        $public = $this->login()->getJson($base.'/checker')->assertOk();
        $this->assertStringNotContainsString($private, $public->getContent());
        $this->assertStringNotContainsString($reference, $public->getContent());
        $this->getJson($base.'/checker/private')->assertForbidden();
        $response = $this->postJson($base.'/code-submissions', [
            'request_id' => (string) Str::uuid(), 'code' => 'print(2+3)',
        ])->assertCreated();
        $this->assertStringNotContainsString($private, $response->getContent());
        $this->assertStringNotContainsString($reference, $response->getContent());
        $snapshot = CodeExecutionRun::findOrFail($response->json('execution_id'))->snapshot;
        $this->assertSame($visible, $snapshot['public_text']);
        $this->assertSame($private, $snapshot['private_instructions']);
        $this->assertSame($reference, $snapshot['reference_answer']);
    }

    public function test_submissions_keep_source_and_issue_only_scoped_socket_tokens(): void
    {
        $result = $this->createSubmission();
        $this->assertSame("  print(2+3)\n", $result['files'][0]['source']);
        $claims = JWT::decode($result['subscription']['token'], new Key($this->secret, 'HS256'));
        $this->assertSame('geekexecutor', $claims->aud);
        $this->assertSame('execution-subscribe', $claims->purpose);
        $this->assertSame($result['execution_id'], $claims->execution_id);
        $this->assertSame('tests',$result['check_type']);
        $this->assertStringNotContainsString('PRIVATE RUBRIC', json_encode($result));
        $this->login(3)->getJson('/insider/code-submissions/'.$result['id'])->assertForbidden();
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private',array_replace($this->configBody(),['check_type'=>'gpt']))->assertOk();
        $this->getJson('/insider/code-submissions/'.$result['id'].'?test_data=metadata')->assertOk()->assertJsonPath('check_type','tests');
        $this->login()->get($result['files'][0]['download_url'])->assertOk()->assertStreamedContent("  print(2+3)\n");
    }

    public function test_suite_diagnostics_with_hidden_data_are_visible_only_to_teachers(): void
    {
        $created=$this->createSubmission();
        $run=CodeExecutionRun::findOrFail($created['execution_id']);
        $run->update(['legacy_report'=>['suite_diagnostics'=>['text'=>'PRIVATE INPUT AND EXPECTED IMAGE','executions'=>3]]]);
        $url='/insider/code-submissions/'.$created['id'];
        $this->login()->getJson($url)->assertOk()->assertJsonPath('checker_diagnostics',null)->assertJsonPath('report',null)
            ->assertDontSee('PRIVATE INPUT AND EXPECTED IMAGE');
        $this->login(1)->getJson($url)->assertOk()->assertJsonPath('checker_diagnostics.text','PRIVATE INPUT AND EXPECTED IMAGE');
        $this->getJson($url.'?test_data=metadata')->assertOk()->assertDontSee('PRIVATE INPUT AND EXPECTED IMAGE')->assertJsonPath('text_report_available',true);
        $this->getJson($url.'/runs/'.$run->id.'?test_data=metadata')->assertOk()->assertDontSee('PRIVATE INPUT AND EXPECTED IMAGE')->assertJsonPath('text_report_available',true);
        $this->getJson($url.'/runs/'.$run->id.'/tests/1')->assertNotFound();
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

    public function test_compiler_diagnostic_is_visible_without_unlocking_private_test_data(): void
    {
        foreach ([false, true] as $large) {
            $submission = $this->createSubmission();
            $run = $submission['execution_id'];
            $payload = $this->report($run);
            $payload['code_id'] = $submission['id'];
            $diagnostic = "Compilation failed: expected ';'".($large ? str_repeat("\ncompiler diagnostic", 6000) : '');
            $payload['tests'][0]['verdict'] = 'compilation_error';
            $payload['tests'][0]['error'] = $large ? app(Artifacts::class)->put($diagnostic) : $this->field($diagnostic);
            app(Reports::class)->accept($payload);
            CodeExecutionRun::findOrFail($run)->update(['state'=>'completed', 'points'=>0, 'finished_at'=>now()]);
            $base = '/insider/code-submissions/'.$submission['id'];
            $report = '/insider/code-test-reports/'.$run;
            foreach ([$base.'?test_data=metadata', $base.'/runs/'.$run.'?test_data=metadata', $report.'?test_data=metadata'] as $url) {
                $response = $this->login()->getJson($url)->assertOk()->assertDontSee('SECRET');
                $data = $response->json('report') ?? $response->json();
                $this->assertFalse($data['tests'][0]['unlocked']);
                $this->assertFalse($data['tests'][0]['can_purchase']);
                foreach (['input', 'expected', 'actual', 'error'] as $field) $this->assertArrayNotHasKey($field, $data['tests'][0]);
                $this->assertSame($large ? 'blob' : 'inline', $data['compilation_error']['type']);
                if (!$large) $this->assertSame($diagnostic, $data['compilation_error']['text']);
                if ($large) $download = $data['compilation_error']['download_url'];
            }
            if ($large) $this->get($download)->assertOk()->assertStreamedContent($diagnostic);
            $this->getJson($base.'/runs/'.$run.'/tests/1')->assertForbidden();
            foreach (['input', 'expected', 'actual'] as $field) $this->getJson($report.'/tests/'.str_repeat('b', 64).'/'.$field)->assertForbidden();
            $this->postJson($report.'/tests/'.str_repeat('b', 64).'/purchase')->assertConflict();
            $this->login(3)->getJson($report)->assertForbidden();
            if ($large) $this->get($download)->assertForbidden();
        }
        $this->assertDatabaseCount('code_test_purchases', 0);
        $this->assertSame(20, (int) User::find(2)->balance());
    }

    public function test_test_metadata_and_purchases_are_body_free_and_single_test_access_is_scoped():void
    {
        $submission=$this->createSubmission();$run=$submission['execution_id'];
        $payload=$this->report($run);$payload['code_id']=$submission['id'];$payload['total']=2;$payload['passed']=1;
        $payload['tests'][]=['number'=>2,'test_id'=>str_repeat('c',64),'is_public'=>true,'verdict'=>'passed',
            'input'=>$this->field('PUBLIC SECOND INPUT'),'expected'=>$this->field('PUBLIC SECOND EXPECTED'),
            'actual'=>$this->field('PUBLIC SECOND ACTUAL'),'error'=>$this->field('PUBLIC SECOND ERROR'),'comparison'=>['equal'=>true],'limits'=>['time'=>1]];
        $artifacts=app(Artifacts::class);
        $testArtifact=$artifacts->put('TEST ARTIFACT THAT MUST NOT BE READ BY METADATA');
        $payload['tests'][1]['error']=$testArtifact;
        app(Reports::class)->accept($payload);
        $this->partialMock(Artifacts::class,function($mock)use($testArtifact){
            $mock->shouldNotReceive('get')->with($testArtifact['blob_id']);
            $mock->shouldNotReceive('readStream')->with($testArtifact['blob_id']);
        });
        CodeExecutionRun::findOrFail($run)->update(['comment'=>'RAW SECRET INPUT AND PUBLIC SECOND INPUT','state'=>'completed','points'=>5,'finished_at'=>now()]);
        $base='/insider/code-submissions/'.$submission['id'];$report='/insider/code-test-reports/'.$run;
        foreach([2,1] as $user){
            $this->login($user);
            foreach([$base.'?test_data=metadata',$base.'/runs/'.$run.'?test_data=metadata',$report.'?test_data=metadata'] as $url){
                $response=$this->getJson($url)->assertOk()->assertDontSee('SECRET INPUT')->assertDontSee('PUBLIC SECOND')->assertDontSee($testArtifact['blob_id'])->json();
                $metadata=$response['report']??$response;
                $this->assertSame($run,$metadata['run_id']);$this->assertSame('metadata',$metadata['test_data']);
                foreach($metadata['tests'] as $test)foreach(['input','expected','actual','error','comparison','limits'] as $field)$this->assertArrayNotHasKey($field,$test);
                $this->assertArrayNotHasKey('configuration',$response);
            }
        }
        app()->instance(Artifacts::class,$artifacts);
        $detail=$this->login()->getJson($base.'/runs/'.$run.'/tests/2')->assertOk()->assertJsonPath('input.text','PUBLIC SECOND INPUT')
            ->assertJsonPath('error.type','inline')->assertJsonPath('error.text','TEST ARTIFACT THAT MUST NOT BE READ BY METADATA')->assertDontSee('SECRET');
        $this->assertArrayNotHasKey('download_url',$detail->json('error'));
        $this->getJson($base.'/runs/'.$run.'/tests/1')->assertForbidden();
        $this->assertDatabaseCount('code_test_purchases',0);$this->assertSame(20,(int)User::find(2)->balance());
        $this->login(3)->getJson($base.'/runs/'.$run.'/tests/2')->assertForbidden();
        $this->login(1)->getJson($base.'/runs/'.$run.'/tests/1')->assertOk()->assertJsonPath('input.text','SECRET INPUT')->assertDontSee('PUBLIC SECOND');
        $other=$this->login()->postJson('/insider/courses/7/tasks/12/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(6)'])->assertCreated()->json();
        $this->getJson('/insider/code-submissions/'.$other['id'].'/runs/'.$run.'/tests/2')->assertNotFound();
        $this->getJson($base.'/runs/'.$run.'/tests/3')->assertNotFound();
        $this->postJson($report.'/tests/'.str_repeat('b',64).'/purchase?test_data=metadata',['expected_price'=>5])->assertOk()
            ->assertJsonPath('charged',5)->assertJsonPath('report.tests.0.unlocked',true)->assertDontSee('SECRET')->assertDontSee('PUBLIC SECOND');
        $this->getJson($base.'/runs/'.$run.'/tests/1')->assertOk()->assertJsonPath('input.text','SECRET INPUT')->assertDontSee('PUBLIC SECOND');
        $this->postJson($report.'/tests/'.str_repeat('b',64).'/purchase?test_data=metadata',['expected_price'=>5])->assertOk()->assertJsonPath('charged',0)->assertDontSee('SECRET');
        $this->assertSame(15,(int)User::find(2)->balance());$this->assertDatabaseCount('code_test_purchases',1);
        // The existing full report API stays compatible for other consumers.
        $this->getJson($report)->assertOk()->assertJsonPath('tests.0.input.text','SECRET INPUT')->assertJsonPath('tests.1.input.text','PUBLIC SECOND INPUT');
    }

    public function test_historical_test_metadata_and_detail_never_borrow_current_task_fixtures():void
    {
        $text="Пройдено тестов: 1 из 2.\n\nТест 1 (публичный): пройден.\n\nВвод:\n<pre>SAVED PUBLIC INPUT</pre>\n\nВывод программы:\n<pre>SAVED PUBLIC OUTPUT</pre>\n\nТест 2 (приватный): неверный ответ.";
        $root=$this->legacyExport();$this->rewriteLegacy($root,['check_comments'=>$text]);
        app(\App\Services\CodeChecking\LegacyImport::class)->run($root,['tasks'=>['12'=>12]],true,4);
        $submission=CodeSubmission::first();$run=$submission->current_run_id;$base='/insider/code-submissions/'.$submission->id;
        foreach([2,1] as $user){
            $this->login($user);
            foreach([$base.'?test_data=metadata',$base.'/runs/'.$run.'?test_data=metadata'] as $url){
                $response=$this->getJson($url)->assertOk()->assertDontSee('SAVED PUBLIC INPUT')->assertDontSee('SAVED PUBLIC OUTPUT')->assertJsonPath('comment','')->assertJsonPath('report.run_id',$run)->json();
                foreach($response['report']['tests'] as $test)foreach(['input','expected','actual','error'] as $field)$this->assertArrayNotHasKey($field,$test);
            }
        }
        $this->login()->getJson($base.'/runs/'.$run.'/tests/1')->assertOk()->assertJsonPath('input.text','SAVED PUBLIC INPUT')->assertJsonPath('actual.text','SAVED PUBLIC OUTPUT');
        $this->getJson($base.'/runs/'.$run.'/tests/2')->assertForbidden();
        $this->login(1)->getJson($base.'/runs/'.$run.'/tests/2')->assertOk()->assertJsonPath('data_unavailable',true)->assertJsonMissingPath('input')->assertJsonMissingPath('actual');
        $this->assertDatabaseCount('code_test_purchases',0);$this->assertSame(20,(int)User::find(2)->balance());
    }

    private function archivedIoRun(): CodeExecutionRun
    {
        $root=$this->legacyExport('tests');
        $task=json_decode(trim(file_get_contents($root.'/tasks.jsonl')),true);
        $task['test_mode']='io';$task['test_config']=['tests'=>[
            ['is_public'=>true,'input'=>'PUBLIC ARCHIVED INPUT','output'=>'PUBLIC ARCHIVED EXPECTED'],
            ['is_public'=>false,'input'=>"  ARCHIVED SECRET INPUT\n",'output'=>'ARCHIVED SECRET EXPECTED'],
            ['is_public'=>false,'input'=>'PASSED SECRET INPUT','output'=>'PASSED SECRET EXPECTED'],
        ]];
        file_put_contents($root.'/tasks.jsonl',json_encode($task)."\n");
        $this->rewriteLegacy($root,['check_comments'=>"Пройдено тестов: 2 из 3.\n\nТест 1 (публичный): пройден.\n\nТест 2 (приватный): неверный ответ.\n\nТест 3 (приватный): пройден."]);
        app(\App\Services\CodeChecking\LegacyImport::class)->run($root,['tasks'=>['12'=>12]],true,4);
        return CodeSubmission::first()->currentRun;
    }

    public function test_archived_test_data_is_scoped_lazy_and_never_borrowed_from_current_definitions(): void
    {
        $run=$this->archivedIoRun();$base='/insider/code-submissions/'.$run->submission_id.'/runs/'.$run->id;
        CodeTaskChecker::whereKey(12)->update(['config'=>['check_type'=>'tests','test_mode'=>'io','test_config'=>['tests'=>[['input'=>'WRONG CURRENT DATA','output'=>'WRONG CURRENT ANSWER']]]]]);
        foreach([1,2] as $user)$this->login($user)->getJson($base.'?test_data=metadata')->assertOk()->assertDontSee('ARCHIVED SECRET')->assertDontSee('PUBLIC ARCHIVED INPUT')->assertDontSee('WRONG CURRENT')->assertJsonPath('report.tests.1.data_source','geekpaste_archive');
        $this->getJson($base.'/tests/1')->assertOk()->assertJsonPath('input.text','PUBLIC ARCHIVED INPUT')->assertJsonPath('expected.text','PUBLIC ARCHIVED EXPECTED');
        $this->getJson($base.'/tests/2')->assertForbidden();
        $this->get($base.'/tests/2/input')->assertForbidden();
        $this->login(3)->getJson($base.'/tests/1')->assertForbidden();
        $teacher=$this->login(1)->getJson($base.'/tests/2')->assertOk()->assertJsonPath('input.text',"  ARCHIVED SECRET INPUT\n")->assertJsonPath('expected.text','ARCHIVED SECRET EXPECTED')->assertJsonMissingPath('actual')->assertDontSee('WRONG CURRENT')->json();
        $this->get($teacher['input']['download_url'])->assertOk()->assertStreamedContent("  ARCHIVED SECRET INPUT\n");
        $this->postJson($base.'/tests/2/purchase',['expected_price'=>5])->assertForbidden();
        $this->assertDatabaseCount('code_test_purchases',0);$this->assertSame(20,(int)User::find(2)->balance());
    }

    public function test_archived_test_purchase_preserves_grades_checks_price_and_reuses_access_between_runs(): void
    {
        $run=$this->archivedIoRun();$base='/insider/code-submissions/'.$run->submission_id.'/runs/'.$run->id;
        $before=$run->only(['points','snapshot','legacy_report','comment']);
        $this->login()->postJson($base.'/tests/2/purchase',['expected_price'=>99])->assertConflict();
        $this->assertSame(20,(int)User::find(2)->balance());
        $this->postJson($base.'/tests/3/purchase',['expected_price'=>5])->assertConflict();
        $this->postJson($base.'/tests/2/purchase',['expected_price'=>5])->assertOk()->assertJsonPath('charged',5)->assertJsonPath('balance',15)->assertDontSee('ARCHIVED SECRET');
        $this->postJson($base.'/tests/2/purchase',['expected_price'=>99])->assertOk()->assertJsonPath('charged',0);
        $copy=$run->replicate(['id']);$copy->id=(string)Str::uuid();$copy->save();
        $other='/insider/code-submissions/'.$run->submission_id.'/runs/'.$copy->id;
        $this->getJson($other.'/tests/2')->assertOk()->assertJsonPath('input.text',"  ARCHIVED SECRET INPUT\n")->assertJsonMissingPath('actual');
        $this->postJson($other.'/tests/2/purchase',['expected_price'=>5])->assertOk()->assertJsonPath('charged',0);
        $this->assertDatabaseCount('code_test_purchases',1);$this->assertSame(15,(int)User::find(2)->balance());
        $this->assertSame($before,$run->fresh()->only(array_keys($before)));
    }

    public function test_archived_test_purchase_never_charges_for_missing_files_disabled_reveals_or_insufficient_balance(): void
    {
        $run=$this->archivedIoRun();$url='/insider/code-submissions/'.$run->submission_id.'/runs/'.$run->id.'/tests/2/purchase';
        CodeTaskChecker::whereKey(12)->update(['allows_test_reveals'=>false]);
        $this->login()->postJson($url,['expected_price'=>5])->assertConflict();
        CodeTaskChecker::whereKey(12)->update(['allows_test_reveals'=>true]);
        DB::table('blocked_tasks')->insert(['task_id'=>12,'course_id'=>7,'user_id'=>2]);
        $this->postJson($url,['expected_price'=>5])->assertConflict();DB::table('blocked_tasks')->delete();
        DB::table('coin_transactions')->insert(['user_id'=>2,'price'=>-20,'comment'=>'Spend']);
        $this->postJson($url,['expected_price'=>5])->assertConflict();
        DB::table('coin_transactions')->insert(['user_id'=>2,'price'=>20,'comment'=>'Restore']);
        $artifact=$run->snapshot['test_config']['tests'][1]['input']['blob_id'];DB::table('code_artifacts')->where('id',$artifact)->delete();DB::table('code_artifact_contents')->where('id',$artifact)->delete();
        $this->postJson($url,['expected_price'=>5])->assertNotFound();
        $this->assertDatabaseCount('code_test_purchases',0);$this->assertSame(20,(int)User::find(2)->balance());
    }

    public function test_incompatible_archived_sets_are_not_matched_by_test_number_or_sold(): void
    {
        $run=$this->archivedIoRun();$base='/insider/code-submissions/'.$run->submission_id.'/runs/'.$run->id;
        $snapshot=$run->snapshot;$snapshot['test_config']['tests'][1]['is_public']=true;
        $run->update(['snapshot'=>$snapshot]);
        $this->login(1)->getJson($base.'/tests/2')->assertOk()->assertJsonPath('data_unavailable',true)->assertJsonMissingPath('input');
        $this->login()->postJson($base.'/tests/2/purchase',['expected_price'=>5])->assertNotFound();
        $snapshot['test_config']['tests']=array_slice($snapshot['test_config']['tests'],0,2);$run->update(['snapshot'=>$snapshot]);
        $this->getJson($base.'?test_data=metadata')->assertOk()->assertJsonPath('report.tests.1.can_purchase',false)->assertJsonMissingPath('report.tests.1.purchase_url');
        $this->assertDatabaseCount('code_test_purchases',0);$this->assertSame(20,(int)User::find(2)->balance());
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
        $teacherView=app(Integrity::class)->view($other,true,User::findOrFail(1));
        $this->assertStringContainsString('student=2', $teacherView['plagiarism']['matches'][0]['review_url']);
        $this->assertStringContainsString('attempt='.$record->id, $teacherView['plagiarism']['matches'][0]['review_url']);
        $foreign=$record->replicate()->forceFill(['id'=>(string)Str::uuid(),'course_id'=>8,'request_id'=>(string)Str::uuid()]);$foreign->save();
        $report=DB::table('code_integrity_reports')->where('submission_id',$other->id)->first();
        $matches=json_decode($report->plagiarism,true);$matches['matches'][]=['submission_id'=>$foreign->id,'percent'=>99];
        DB::table('code_integrity_reports')->where('submission_id',$other->id)->update(['plagiarism'=>json_encode($matches)]);
        $teacherView=app(Integrity::class)->view($other,true,User::findOrFail(1));
        $this->assertArrayNotHasKey('review_url',$teacherView['plagiarism']['matches'][1]);
        $this->assertArrayNotHasKey('user_name',$teacherView['plagiarism']['matches'][1]);
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

    public function test_author_retries_a_technical_failure_for_free_with_immutable_source_and_configuration(): void
    {
        $created = $this->createSubmission();
        $record = CodeSubmission::findOrFail($created['id']);
        $before = $record->only(['files', 'answer_text', 'source_metadata', 'payload_hash', 'solution_id']);
        $snapshot = $record->currentRun->snapshot;
        $coins = DB::table('coin_transactions')->count();
        $this->mock(Grading::class)->shouldNotReceive('apply');
        $result = ['execution_id' => $created['execution_id'], 'state' => 'execution error', 'points' => 0,
            'comment' => 'PRIVATE TEST INPUT traceback https://secret.test?token=secret'];
        $this->service('POST', '/api/executor/executions/'.$created['execution_id'].'/result', $result)->assertOk();
        $base = '/insider/code-submissions/'.$created['id'];
        $this->login()->getJson($base.'?test_data=metadata')->assertOk()->assertJsonPath('comment', '')
            ->assertJsonPath('execution_error.code', 'execution_error')->assertJsonPath('execution_error.can_retry', true)
            ->assertJsonPath('execution_error.retry_url', url($base.'/retry'))->assertJsonPath('text_report_available', false)
            ->assertDontSee('PRIVATE TEST')->assertDontSee('traceback')->assertDontSee('secret.test');
        $this->getJson($base.'/runs/'.$created['execution_id'].'?test_data=metadata')->assertJsonPath('execution_error.can_retry', true)->assertDontSee('PRIVATE TEST');
        $this->getJson($base)->assertJsonPath('comment', '')->assertDontSee('PRIVATE TEST')->assertDontSee('secret.test');
        $this->getJson($base.'/runs/'.$created['execution_id'])->assertJsonPath('comment', '')->assertDontSee('PRIVATE TEST')->assertDontSee('secret.test');
        $this->login(3)->postJson($base.'/retry')->assertForbidden();
        $this->login()->postJson($base.'/retry', ['latest_configuration' => true])->assertUnprocessable();
        $this->postJson($base.'/retry', ['code' => 'new source'])->assertUnprocessable();
        // Changed task settings must not affect a technical retry.
        CodeTaskChecker::whereKey(12)->update(['config' => ['language' => 'cpp', 'check_type' => 'gpt']]);
        Queue::fake();
        $next = $this->postJson($base.'/retry')->assertStatus(202)->json();
        $this->assertNotSame($created['execution_id'], $next['execution_id']);
        $this->assertSame(array_replace($snapshot, ['legacy'=>false]), CodeExecutionRun::findOrFail($next['execution_id'])->snapshot);
        $this->assertSame($before, $record->fresh()->only(array_keys($before)));
        $this->assertSame('error', CodeExecutionRun::findOrFail($created['execution_id'])->state);
        $this->assertSame($snapshot, CodeExecutionRun::findOrFail($created['execution_id'])->snapshot);
        $this->assertNotNull(CodeExecutionRun::findOrFail($created['execution_id'])->result_hash);
        $this->assertSame(1, CodeSubmission::count());
        $this->assertSame(2, CodeExecutionRun::count());
        $this->assertSame($coins, DB::table('coin_transactions')->count());
        Queue::assertPushed(\App\Jobs\DispatchCodeExecution::class, 1);
        $this->postJson($base.'/retry')->assertConflict();
        $this->getJson($base.'/runs/'.$created['execution_id'].'?test_data=metadata')->assertJsonPath('execution_error.can_retry', false);
    }

    public function test_technical_retry_requires_a_current_error_with_ready_unblocked_source(): void
    {
        $created = $this->createSubmission();
        $url = '/insider/code-submissions/'.$created['id'].'/retry';
        $run = CodeExecutionRun::findOrFail($created['execution_id']);
        $this->postJson($url)->assertConflict();
        $run->update(['state' => 'completed', 'finished_at' => now()]);
        $this->postJson($url)->assertConflict();
        $run->update(['state' => 'error']);
        DB::table('blocked_tasks')->insert(['task_id'=>12, 'course_id'=>7, 'user_id'=>2]);
        $this->postJson($url)->assertConflict();
        DB::table('blocked_tasks')->delete();
        CodeSubmission::findOrFail($created['id'])->update(['preparation_state'=>'failed']);
        $this->postJson($url)->assertConflict();
        CodeSubmission::findOrFail($created['id'])->update(['preparation_state'=>'ready']);
        $this->login(1)->postJson($url)->assertStatus(202);
    }

    public function test_stronger_ai_recheck_uses_only_the_admin_model_and_preserves_the_original_run_and_source(): void
    {
        config(['ai.shared_models' => true, 'ai.defaults.recheck' => ['model' => 'strong-model', 'timeout' => 150]]);
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language'=>'python', 'check_type'=>'gpt', 'gpt_model'=>'original-model', 'gpt_rate_limit'=>1,
            'private_instructions'=>'PRIVATE FROZEN RUBRIC'])->assertOk();
        $created = $this->login()->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'code'=>'print(5)'])->assertCreated()->json();
        $record = CodeSubmission::findOrFail($created['id']);
        $before = $record->only(['files', 'answer_text', 'source_metadata', 'payload_hash']);
        $old = $record->currentRun;
        $old->update(['state'=>'completed', 'points'=>6, 'finished_at'=>now(), 'comment'=>'Original feedback']);
        $snapshot = $old->snapshot;
        $url = '/insider/code-submissions/'.$record->id;
        $this->getJson($url.'?test_data=metadata')->assertJsonPath('ai_recheck', null)->assertDontSee('strong-model');
        $this->postJson($url.'/recheck', ['stronger_ai'=>true])->assertForbidden();
        $this->login(1)->getJson($url.'?test_data=metadata')->assertJsonPath('ai_recheck.available', true)->assertJsonPath('ai_recheck.model', 'strong-model');
        $this->postJson($url.'/recheck', ['stronger_ai'=>true, 'gpt_model'=>'injected-model'])->assertUnprocessable();
        $this->postJson($url.'/recheck', ['stronger_ai'=>true, 'latest_configuration'=>true])->assertUnprocessable();
        // Current instructions and mode are irrelevant to this frozen recheck.
        CodeTaskChecker::whereKey(12)->update(['config'=>['check_type'=>'tests', 'language'=>'cpp']]);
        Queue::fake();
        $next = $this->postJson($url.'/recheck', ['stronger_ai'=>true])->assertStatus(202)->json();
        $run = CodeExecutionRun::findOrFail($next['execution_id']);
        $expected = array_replace($snapshot, ['legacy'=>false, 'gpt_model'=>'strong-model', 'gpt_timeout'=>150,
            'ai_review'=>['source_run_id'=>$old->id, 'requested_by'=>1, 'previous_model'=>'original-model', 'model'=>'strong-model', 'timeout'=>150]]);
        $this->assertSame($expected, $run->snapshot);
        $this->assertSame($snapshot, $old->fresh()->snapshot);
        $this->assertSame('Original feedback', $old->fresh()->comment);
        $this->assertSame($before, $record->fresh()->only(array_keys($before)));
        $input = app(\App\Services\CodeChecking\Submissions::class)->input($run);
        $this->assertSame('strong-model', $input['task']['gpt_model']);
        $this->assertSame(150, $input['task']['gpt_timeout']);
        $this->assertSame('PRIVATE FROZEN RUBRIC', $input['task']['private_instructions']);
        Queue::assertPushed(\App\Jobs\DispatchCodeExecution::class, 1);
        $this->assertSame(1, CodeSubmission::count());
        $run->update(['state'=>'completed', 'finished_at'=>now()]);
        $again = $this->postJson($url.'/recheck', ['stronger_ai'=>true])->assertStatus(202)->json();
        $strongAgain = CodeExecutionRun::findOrFail($again['execution_id']);
        $strongAgain->update(['state'=>'completed', 'finished_at'=>now()]);
        $normal = $this->postJson($url.'/recheck')->assertStatus(202)->json();
        $ordinary = CodeExecutionRun::findOrFail($normal['execution_id']);
        $this->assertSame(array_replace($snapshot, ['legacy'=>false]), $ordinary->snapshot);
        $this->assertSame($expected, $run->fresh()->snapshot);
        $this->assertSame($before, $record->fresh()->only(array_keys($before)));
    }

    public function test_recheck_comment_reaches_ai_without_overwriting_the_frozen_rubric_or_previous_note(): void
    {
        $base='/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private',['language'=>'python','check_type'=>'gpt','private_instructions'=>'Frozen rubric'])->assertOk();
        $created=$this->login()->postJson($base.'/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(5)'])->assertCreated()->json();
        $old=CodeExecutionRun::findOrFail($created['execution_id']);
        $old->update(['state'=>'completed','finished_at'=>now()]);
        $snapshot=$old->snapshot;
        $url='/insider/code-submissions/'.$created['id'].'/recheck';
        $this->postJson($url,['comment'=>'Student cannot trigger a recheck'])->assertForbidden();
        $this->login(1)->postJson($url,['comment'=>str_repeat('x',4001)])->assertUnprocessable();
        $first=$this->postJson($url,['comment'=>'Check the shadow ray'])->assertStatus(202)->json();
        $run=CodeExecutionRun::findOrFail($first['execution_id']);
        $this->assertSame('Check the shadow ray',$run->snapshot['review_comment']);
        $this->assertStringContainsString('Check the shadow ray',$run->snapshot['private_instructions']);
        $this->assertSame('Frozen rubric',$run->snapshot['review_instructions_original']);
        $this->assertSame($snapshot,$old->fresh()->snapshot);
        $this->getJson('/insider/code-submissions/'.$created['id'].'/runs/'.$run->id.'?test_data=metadata')->assertOk()->assertJsonPath('review_comment','Check the shadow ray');
        $run->update(['state'=>'completed','finished_at'=>now()]);
        $second=$this->postJson($url,['comment'=>'Check the second criterion'])->assertStatus(202)->json();
        $next=CodeExecutionRun::findOrFail($second['execution_id']);
        $this->assertStringNotContainsString('Check the shadow ray',$next->snapshot['private_instructions']);
        $this->assertStringContainsString('Frozen rubric',$next->snapshot['private_instructions']);
        $this->assertStringContainsString('Check the second criterion',$next->snapshot['private_instructions']);
        $this->assertSame(1,CodeSubmission::count());
    }

    public function test_stronger_ai_never_converts_a_frozen_test_check_and_requires_a_configured_model(): void
    {
        config(['ai.defaults.recheck'=>['model'=>'strong-model', 'timeout'=>180]]);
        $created = $this->createSubmission();
        $run = CodeExecutionRun::findOrFail($created['execution_id']);
        $run->update(['state'=>'completed', 'finished_at'=>now()]);
        $url = '/insider/code-submissions/'.$created['id'];
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private', ['language'=>'python', 'check_type'=>'gpt'])->assertOk();
        $this->getJson($url.'?test_data=metadata')->assertJsonPath('check_type','tests')->assertJsonPath('ai_recheck',null);
        $this->postJson($url.'/recheck', ['stronger_ai'=>true])->assertConflict();
        $this->assertSame(1, CodeExecutionRun::count());
        $run->update(['snapshot'=>array_replace($run->snapshot,['check_type'=>'gpt'])]);
        config(['ai.defaults.recheck.model'=>null]);
        $this->postJson($url.'/recheck', ['stronger_ai'=>true])->assertConflict();
        $this->assertSame(1, CodeExecutionRun::count());
    }

    public function test_saved_markdown_is_readable_and_bundle_contains_only_exact_authorized_files():void
    {
        $created=$this->createSubmission();$record=CodeSubmission::findOrFail($created['id']);$artifacts=app(Artifacts::class);
        $readme="# Проект\n\nЗапуск: `python main.py`\n";$binary="\x89PNG\0original";
        $files=[['name'=>'project/README.md','artifact'=>$artifacts->put($readme)],['name'=>'project/picture.png','artifact'=>$artifacts->put($binary)]];
        $record->update(['files'=>$files]);$base='/insider/code-submissions/'.$record->id;
        $detail=$this->getJson($base)->assertOk()->assertJsonPath('files.0.source',$readme)->json();
        $this->assertArrayNotHasKey('source',$detail['files'][1]);
        $url=$detail['bundle_url'];$this->login(3)->get($url)->assertForbidden();
        foreach([2,1] as $viewer){
            $response=$this->login($viewer)->get($url)->assertOk()->assertHeader('Content-Type','application/zip');
            $path=$response->baseResponse->getFile()->getPathname();$zip=new \ZipArchive();$this->assertTrue($zip->open($path));
            $this->assertSame(2,$zip->numFiles);$this->assertSame($readme,$zip->getFromName('project/README.md'));
            $this->assertSame($binary,$zip->getFromName('project/picture.png'));$zip->close();unlink($path);
        }
        $record->update(['files'=>[['name'=>'../escape.txt','artifact'=>$artifacts->put('unsafe path')],$files[1]]]);
        $this->get($url)->assertUnprocessable();
        $this->assertSame($readme,$artifacts->get($files[0]['artifact']['blob_id']));
    }

    public function test_historical_ai_recheck_binds_the_selected_attempt_and_rejects_other_sources():void
    {
        config(['ai.defaults.recheck'=>['model'=>'strong-model','timeout'=>180]]);
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,raw_mark INTEGER,comment TEXT,submitted TEXT,checked TEXT)');
        $created=$this->createSubmission();$record=CodeSubmission::findOrFail($created['id']);
        $record->update(['legacy_id'=>'SAVED','source_metadata'=>['legacy_id'=>'SAVED'],'solution_id'=>502]);
        $url='/insider/code-submissions/'.$record->id;
        foreach([[501,2,5],[502,2,6],[503,3,9]] as [$id,$user,$points])DB::table('solutions')->insert(['id'=>$id,'user_id'=>$user,'course_id'=>7,'task_id'=>12,
            'text'=>url($url),'mark'=>$points,'raw_mark'=>$points,'comment'=>'Original '.$id,'submitted'=>'2026-11-10 12:00:00','checked'=>'2026-11-10 12:01:00']);
        $old=$record->currentRun;$snapshot=array_replace($old->snapshot,['check_type'=>'gpt','gpt_model'=>'original-model']);
        $old->update(['state'=>'completed','points'=>6,'finished_at'=>now(),'snapshot'=>$snapshot]);
        $before=$record->only(['files','answer_text','source_metadata','solution_id']);
        $this->postJson($url.'/recheck',['stronger_ai'=>true,'review_solution_id'=>501])->assertForbidden();
        $this->login(1)->postJson($url.'/recheck',['stronger_ai'=>true])->assertConflict();
        $this->postJson($url.'/recheck',['stronger_ai'=>true,'review_solution_id'=>503])->assertConflict();
        $response=$this->postJson($url.'/recheck',['stronger_ai'=>true,'review_solution_id'=>501,'comment'=>'Проверьте запуск'])->assertStatus(202)->json();
        $run=CodeExecutionRun::findOrFail($response['execution_id']);
        $this->assertSame(501,$run->snapshot['review_solution_id']);$this->assertSame('strong-model',$run->snapshot['gpt_model']);
        $this->assertSame($before,$record->fresh()->only(array_keys($before)));$this->assertSame($snapshot,$old->fresh()->snapshot);
        $this->mock(Grading::class)->shouldReceive('apply')->once()->withArgs(fn($submission,$data)=>(int)$submission->solution_id===501 && $submission->id===$record->id && $data['points']===8);
        $data=['execution_id'=>$run->id,'state'=>'done','points'=>8,'comment'=>'New review'];
        app(\App\Services\CodeChecking\Results::class)->accept($run,$data,hash('sha256',json_encode($data)));
        $this->assertSame('Original 502',DB::table('solutions')->where('id',502)->value('comment'));
        $normal=$this->postJson($url.'/recheck',['review_solution_id'=>501])->assertStatus(202)->json();
        $restored=CodeExecutionRun::findOrFail($normal['execution_id']);
        $this->assertSame('original-model',$restored->snapshot['gpt_model']);$this->assertSame(501,$restored->snapshot['review_solution_id']);
    }

    public function test_manual_grade_does_not_remove_original_ai_models_from_recheck():void
    {
        config(['ai.defaults.recheck'=>['model'=>'strong-model','timeout'=>180]]);
        $this->login(1)->putJson('/insider/courses/7/tasks/12/checker/private',['language'=>'python','check_type'=>'gpt','private_instructions'=>'Original rubric','gpt_model'=>'original-model'])->assertOk();
        $created=$this->login()->postJson('/insider/courses/7/tasks/12/code-submissions',['request_id'=>(string)Str::uuid(),'code'=>'print(5)'])->assertCreated()->json();
        $old=CodeExecutionRun::findOrFail($created['execution_id']);$old->update(['state'=>'completed','points'=>6,'finished_at'=>now()]);
        $url='/insider/code-submissions/'.$created['id'];$this->login(1)->postJson($url.'/manual-review')->assertCreated();
        $manual=CodeSubmission::findOrFail($created['id'])->currentRun;$manual->update(['state'=>'completed','points'=>7,'finished_at'=>now()]);
        $this->getJson($url.'?test_data=metadata')->assertOk()->assertJsonPath('check_type','manual')->assertJsonPath('recheck_type','gpt')->assertJsonPath('ai_recheck.available',true);
        $next=$this->postJson($url.'/recheck',['stronger_ai'=>true])->assertStatus(202)->json();$strong=CodeExecutionRun::findOrFail($next['execution_id']);
        $this->assertSame('gpt',$strong->snapshot['check_type']);$this->assertSame('strong-model',$strong->snapshot['gpt_model']);
        $this->assertSame($old->id,$strong->snapshot['ai_review']['source_run_id']);$this->assertSame('Original rubric',$strong->snapshot['private_instructions']);
        $strong->update(['state'=>'completed','finished_at'=>now()]);$normal=$this->postJson($url.'/recheck')->assertStatus(202)->json();
        $restored=CodeExecutionRun::findOrFail($normal['execution_id']);$this->assertSame($old->snapshot['gpt_model']??null,$restored->snapshot['gpt_model']??null);
        $this->assertSame('manual',$manual->fresh()->snapshot['check_type']);$this->assertSame(7,$manual->fresh()->points);
    }

    public function test_new_retry_and_stronger_ai_actions_cannot_overwrite_an_ambiguous_historical_grade(): void
    {
        config(['ai.defaults.recheck'=>['model'=>'strong-model', 'timeout'=>180]]);
        DB::statement('CREATE TABLE solutions (id INTEGER PRIMARY KEY,user_id INTEGER,course_id INTEGER,task_id INTEGER,text TEXT,mark INTEGER,raw_mark INTEGER,comment TEXT,submitted TEXT,checked TEXT)');
        $created = $this->createSubmission();
        $record = CodeSubmission::findOrFail($created['id']);
        $record->update(['legacy_id'=>'MUTABLE_SOURCE','source_metadata'=>['legacy_id'=>'MUTABLE_SOURCE'],'solution_id'=>501]);
        DB::table('solutions')->insert(['id'=>501,'user_id'=>2,'course_id'=>7,'task_id'=>12,'text'=>url('/insider/code-submissions/'.$record->id),
            'mark'=>5,'raw_mark'=>5,'comment'=>'Historical feedback','submitted'=>'2026-11-10 12:00:00','checked'=>'2026-11-10 12:01:00']);
        $before = DB::table('solutions')->where('id',501)->first();
        $run = $record->currentRun;
        $snapshot = array_replace($run->snapshot,['check_type'=>'gpt']);
        $run->update(['state'=>'error','points'=>0,'finished_at'=>now(),'snapshot'=>$snapshot]);
        $url = '/insider/code-submissions/'.$record->id;
        $this->getJson($url.'?test_data=metadata')->assertJsonPath('execution_error.can_retry',false)->assertJsonPath('execution_error.retry_url',null);
        $this->postJson($url.'/retry')->assertConflict();
        $this->login(1)->getJson($url.'?test_data=metadata')->assertJsonPath('ai_recheck.available',true)
            ->assertJsonPath('ai_recheck.requires_review_solution',true);
        $this->postJson($url.'/retry')->assertConflict();
        $this->postJson($url.'/recheck',['stronger_ai'=>true])->assertConflict();
        $this->assertSame($run->id,$record->fresh()->current_run_id);
        $this->assertSame($snapshot,$run->fresh()->snapshot);
        $this->assertEquals($before,DB::table('solutions')->where('id',501)->first());
        $this->assertSame(1,CodeExecutionRun::count());
        // The existing explicit teacher recheck contract is unchanged.
        $this->postJson($url.'/recheck')->assertStatus(202);
        $this->assertEquals($before,DB::table('solutions')->where('id',501)->first());
    }

    public function test_failed_ai_retry_does_not_consume_another_attempt_or_coins(): void
    {
        $base = '/insider/courses/7/tasks/12';
        $this->login(1)->putJson($base.'/checker/private', ['language'=>'python', 'check_type'=>'gpt', 'gpt_rate_limit'=>1])->assertOk();
        $created = $this->login()->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'code'=>'print(5)'])->assertCreated()->json();
        CodeExecutionRun::findOrFail($created['execution_id'])->update(['state'=>'error','finished_at'=>now()]);
        $this->postJson($base.'/code-submissions', ['request_id'=>(string)Str::uuid(), 'code'=>'print(6)'])->assertStatus(429);
        $coins = DB::table('coin_transactions')->count();
        $credits = DB::table('code_attempt_credits')->count();
        $this->postJson('/insider/code-submissions/'.$created['id'].'/retry')->assertStatus(202);
        $this->assertSame($coins, DB::table('coin_transactions')->count());
        $this->assertSame($credits, DB::table('code_attempt_credits')->count());
        $this->assertSame(1, CodeSubmission::count());
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
        $this->putJson('/insider/code-submissions/'.$id.'/flags',['is_public'=>true])->assertForbidden();
        $this->assertFalse(CodeSubmission::findOrFail($id)->is_public);
        // Previously shared work remains accessible until its author revokes it.
        CodeSubmission::findOrFail($id)->update(['is_public'=>true]);
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

    public function test_teachers_can_publish_only_their_own_solutions(): void
    {
        $student=$this->createSubmission();
        $base='/insider/courses/7/tasks/12';
        $teacher=$this->login(1)->postJson($base.'/code-submissions', ['request_id'=>(string) Str::uuid(),
            'code'=>'print(42)'])->assertCreated()->json();
        $this->putJson('/insider/code-submissions/'.$student['id'].'/flags',['is_public'=>true])->assertForbidden();
        $this->putJson('/insider/code-submissions/'.$teacher['id'].'/flags',['is_public'=>true])->assertOk();
        $this->get('/shared/code-submissions/'.$teacher['id'])->assertOk()->assertSee('print(42)');
        $this->putJson('/insider/code-submissions/'.$teacher['id'].'/flags',['is_public'=>false])->assertOk();
        $this->get('/shared/code-submissions/'.$teacher['id'])->assertNotFound();
        $this->login(4)->putJson('/insider/code-submissions/'.$student['id'].'/flags',['is_public'=>true])->assertForbidden();
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
        $this->getJson('/insider/code-submissions/'.$id.'?test_data=metadata')->assertOk()->assertJsonPath('review',null);
        $review=$this->login(1)->getJson('/insider/code-submissions/'.$id.'?test_data=metadata')->assertOk()->json('review');
        $this->assertSame(64,strlen($review['token']));
        $this->postJson($review['skip_url'],['review_token'=>$review['token']])->assertOk()->assertJsonPath('skipped',true);
        $this->getJson('/insider/code-submissions/'.$id.'?test_data=metadata')->assertJsonPath('review',null);
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
