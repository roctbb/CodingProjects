<?php

namespace Tests\Feature;

use App\ProgramStep;
use App\Services\Tutor\Reading;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Services\Tutor\Responder;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class StudentTutorTest extends DeadlineFeatureCase
{
    private const URL = '/insider/courses/1/steps/1/tutor';

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasColumn('code_execution_runs', 'criteria')) {
            (require database_path('migrations/2026_11_21_221000_add_execution_criteria.php'))->up();
        }
        (require database_path('migrations/2026_11_21_230000_create_student_tutor_usage.php'))->up();
        (require database_path('migrations/2026_11_22_003000_add_tutor_budget.php'))->up();
        config(['tutor.enabled' => true, 'tutor.guided_only' => true]);
        $this->actingAs(User::find(1));
    }

    private int $calls = 0;

    private function stubTutor(callable $reply): void
    {
        $this->mock(Responder::class)->shouldReceive('reply')->andReturnUsing(function (array $input) use ($reply) {
            $this->calls++;
            return $reply($input);
        });
    }

    private const USAGE = ['complete'=>true, 'known_tokens'=>132, 'total_tokens'=>132, 'input_tokens'=>112, 'output_tokens'=>20,
        'calls'=>[['stage'=>'answer', 'input_tokens'=>112, 'output_tokens'=>20]]];

    private function question(array $extra = []): array
    {
        return $extra + ['request_id' => (string) Str::uuid(), 'question' => 'Как работает индекс пикселя?'];
    }

    public function test_students_share_daily_and_monthly_reservations_even_after_an_interrupted_call(): void
    {
        config(['tutor.daily_tokens'=>500, 'tutor.platform_daily_tokens'=>600, 'tutor.platform_monthly_tokens'=>750]);
        $usage = app(\App\Services\Tutor\Usage::class);
        $empty = ['complete'=>true, 'known_tokens'=>0];
        $one = $usage->begin(1, (string) Str::uuid());
        $two = $usage->begin(3, (string) Str::uuid());
        $usage->reserve($one, 400, $empty);
        $reject = function ($id, $amount) use ($usage, $empty) {
            try {
                $usage->reserve($id, $amount, $empty);
                $this->fail('The call should exceed the budget.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(429, $e->getStatusCode());
            }
        };
        $reject($two, 201);
        $usage->checkpoint($one, self::USAGE);
        $usage->reserve($one, 200, self::USAGE);
        $usage->finish($one, null);
        $reject($two, 269);
        $usage->reserve($two, 268, $empty);
        $usage->finish($two, self::USAGE);
        $this->travel(1)->days();
        $next = $usage->begin(3, (string) Str::uuid());
        $reject($next, 287);
        $usage->reserve($next, 286, $empty);
    }

    public function test_budget_denial_before_the_model_sends_no_external_request(): void
    {
        config(['tutor.guided_only'=>false, 'tutor.platform_monthly_tokens'=>1,
            'ai.providers.openai.key'=>'test', 'ai.providers.openai.base_url'=>'https://tutor.example']);
        \Illuminate\Support\Facades\Http::fake();
        $this->postJson(self::URL, $this->question())->assertStatus(429);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertDatabaseHas('student_tutor_usage',['state'=>'complete','known_tokens'=>0]);
    }

    public function test_an_existing_meter_uses_the_new_admin_limit_before_the_next_call(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ai_settings')) {
            (require database_path('migrations/2026_10_14_130000_create_ai_settings_table.php'))->up();
        }
        $settings = app(\App\Services\Ai\AiSettings::class);
        $meter = app(\App\Services\Tutor\Usage::class);
        $id = $meter->begin(1, (string) Str::uuid());
        $meter->reserve($id, 200, ['complete'=>true, 'known_tokens'=>0]);
        $settings->save(['tutor_limits'=>['platform_monthly_tokens'=>200]], [], 1);
        try {
            $meter->reserve($id, 100, self::USAGE);
            $this->fail('The existing meter should enforce the new monthly limit.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
        $settings->save(['tutor_limits'=>['platform_monthly_tokens'=>232]], [], 1);
        $meter->reserve($id, 100, self::USAGE);
        $this->assertDatabaseHas('student_tutor_usage', ['id'=>$id, 'reserved_tokens'=>232]);
    }

    public function test_telegram_question_delivery_is_addressed_scoped_and_idempotent(): void
    {
        $queue = \Illuminate\Support\Facades\Queue::class;
        $notification = \App\Jobs\NotifyTaskQuestionTeacher::class;
        $assistant = \App\Jobs\AnswerTaskQuestion::class;
        $url = '/insider/courses/1/tasks/11/chat/1';
        $post = fn (string $body) => ['body'=>$body, 'client_id'=>(string)Str::uuid()];
        $this->postJson($url, $post('Как начать?'))->assertOk();
        $queue::assertPushed($assistant, 1);
        $queue::assertNotPushed($notification);

        $message = $post('@Преподаватель, почему так?') + ['attempt'=>'solution-'.$this->solution()->id];
        $this->postJson($url, $message)->assertOk();
        $this->postJson($url, $message)->assertOk();
        $queue::assertPushed($assistant, 1);
        $queue::assertPushed($notification, 1);
        $job = $queue::pushed($notification)->sole();
        $this->assertSame('notifications', $job->queue);
        $this->assertSame(2, $job->teacherId);
        DB::table('users')->where('id', 2)->update(['telegram_chat_id'=>'12345']);
        \Illuminate\Support\Facades\Http::fake(['https://telegram.example/*'=>\Illuminate\Support\Facades\Http::response(['ok'=>true])]);
        app()->call([$job, 'handle']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && $request['chat_id'] === '12345' && str_contains($request['text'], $message['body'])
            && str_contains($request['text'], 'student=1&task=11&attempt='.$message['attempt'].'#task11'));
        DB::table('course_teachers')->where(['course_id'=>1,'user_id'=>2])->delete();
        app()->call([$job, 'handle']);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        DB::table('course_teachers')->insert(['course_id'=>1,'user_id'=>2]);
        config(['tutor.enabled'=>false]);
        $this->postJson($url, $post('Подскажите, с чего начать'))->assertOk();
        $queue::assertPushed($notification, 2);
        $queue::assertPushed($assistant, 1);
        $this->actingAs(User::findOrFail(2))->postJson($url, $post('Начни с ввода.'))->assertOk();
        $queue::assertPushed($notification, 2);
    }

    public function test_assistant_uses_the_persisted_task_dialogue_and_reports_outstanding_replies(): void
    {
        $root = '/insider/courses/1/tasks/11/chat/';
        $post = fn (int $student, string $body) => $this->postJson($root.$student,
            ['body'=>$body, 'client_id'=>(string) Str::uuid()])->assertOk();
        $state = fn (bool $pending) => $this->getJson($root.'1')->assertOk()->assertJsonPath('awaiting_tutor', $pending);
        $job = fn () => \Illuminate\Support\Facades\Queue::pushed(\App\Jobs\AnswerTaskQuestion::class)->last();
        $answer = "Сначала найди разницу.\n\nНапример, 300 − 100 = 200.";
        $histories = [];
        $this->stubTutor(function ($payload) use (&$histories, $answer) {
            $histories[] = $payload['history'];
            return ['answer'=>$answer, 'sources'=>[], 'guided'=>false, 'usage'=>self::USAGE];
        });

        $post(1, '@преподаватель, что прочитать?');
        $state(false);
        $this->actingAs(User::findOrFail(2));
        $post(1, 'Посмотри на соседние отсчёты.');
        $post(2, 'Другой личный чат.');
        $this->actingAs(User::findOrFail(1));
        $post(1, 'Как найти разницу?');
        $state(true);
        $job()->handle(app(\App\Services\Tutor\Conversation::class));
        $state(false);
        $this->getJson($root.'1')->assertJsonFragment(['text'=>$answer]);
        \Illuminate\Support\Facades\Cache::flush();

        $post(1, 'А что с ней делать дальше?');
        $followup = $job();
        $post(1, 'Покажи ещё один пример.');
        $last = $job();
        $followup->handle(app(\App\Services\Tutor\Conversation::class));
        $state(true);
        $expected = [
            ['role'=>'student', 'text'=>'@преподаватель, что прочитать?'],
            ['role'=>'teacher', 'text'=>'Посмотри на соседние отсчёты.'],
            ['role'=>'student', 'text'=>'Как найти разницу?'],
            ['role'=>'assistant', 'text'=>$answer],
        ];
        $this->assertSame($expected, $histories[1]);
        $last->failed(new \RuntimeException('Interrupted'));
        $state(false);
    }

    public function test_teacher_personal_conversation_and_submission_comments_use_separate_threads(): void
    {
        $this->actingAs(User::findOrFail(2));
        config(['tutor.courses'=>['1']]);
        $root = '/insider/courses/1/tasks/11/chat/';
        $work = $this->solution();
        $this->postJson($root.'1', ['body'=>'Посмотри на строку 3.', 'attempt'=>'solution-'.$work->id,
            'client_id'=>(string)Str::uuid()])->assertOk();
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\AnswerTaskQuestion::class);
        $this->postJson($root.'2', ['body'=>'Как приступить?', 'client_id'=>(string)Str::uuid()])->assertOk();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\AnswerTaskQuestion::class);
        $this->stubTutor(fn () => ['answer'=>'Начни с маленького примера.', 'sources'=>[], 'guided'=>false, 'usage'=>self::USAGE]);
        $id = DB::table('task_chat_messages')->where('student_id',2)->value('id');
        (new \App\Jobs\AnswerTaskQuestion($id))->handle(app(\App\Services\Tutor\Conversation::class));
        $this->getJson($root.'2')->assertOk()->assertJsonFragment(['text'=>'Начни с маленького примера.'])->assertJsonMissing(['text'=>'Посмотри на строку 3.']);
        $this->actingAs(User::findOrFail(1))->getJson($root.'2')->assertForbidden();
    }

    public function test_each_student_and_the_whole_platform_have_a_daily_coin_limit(): void
    {
        config(['tutor.question_coin_chance'=>100, 'tutor.daily_coins'=>1, 'tutor.platform_daily_coins'=>2]);
        $this->stubTutor(fn () => ['answer'=>'Сравни результат.', 'sources'=>[], 'guided'=>false, 'good_question'=>true, 'usage'=>self::USAGE]);
        $send = function ($student) {
            $key = (string) Str::uuid();
            app(\App\Services\TaskChat::class)->send(1, 11, $student, $student, ['body'=>'Как проверить результат?', 'client_id'=>$key]);
            $id = DB::table('task_chat_messages')->where('client_id',$key)->value('id');
            (new \App\Jobs\AnswerTaskQuestion($id))->handle(app(\App\Services\Tutor\Conversation::class));
            return json_decode(DB::table('task_chat_messages')->where('answer_to',$id)->value('tutor_details'),true)['coins'] ?? 0;
        };
        $this->assertSame(1, $send(1));
        $this->assertSame(0, $send(1));
        config(['tutor.platform_daily_coins'=>1]);
        $this->assertSame(0, $send(3));
        $this->travel(1)->days();
        $this->assertSame(1, $send(3));
    }

    public function test_blocking_the_second_call_keeps_the_first_calls_usage(): void
    {
        config(['tutor.guided_only'=>false, 'ai.providers.openai.key'=>'test', 'ai.providers.openai.base_url'=>'https://tutor.example']);
        \Illuminate\Support\Facades\Http::fake(function () {
            config(['tutor.platform_daily_tokens'=>1]);
            $draft = ['answer'=>'Проверь координаты.', 'sources'=>[], 'card'=>null];
            return \Illuminate\Support\Facades\Http::response(['status'=>'completed',
                'usage'=>['input_tokens'=>100, 'output_tokens'=>32, 'total_tokens'=>132],
                'output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($draft)]]]]]);
        });
        $this->postJson(self::URL, $this->question())->assertStatus(429);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $this->assertDatabaseHas('student_tutor_usage',['state'=>'complete','total_tokens'=>132,'known_tokens'=>132]);
    }

    public function test_good_question_coin_is_recorded_once_with_the_chat_reply(): void
    {
        $this->stubTutor(fn ($data) => [
            'answer'=>'Проверь координаты на маленьком примере.', 'sources'=>[], 'guided'=>false,
            'good_question'=>str_contains($data['question'], 'координаты'), 'usage'=>self::USAGE,
        ]);
        $before = (int)User::findOrFail(1)->balance();
        foreach ([[100, 'Почему координаты поменялись местами?', true],
                  [100, 'Дай решение.', false], [0, 'Что означают координаты?', false]] as [$chance, $body, $won]) {
            config(['tutor.question_coin_chance'=>$chance]);
            app(\App\Services\TaskChat::class)->send(1, 11, 1, 1, ['body'=>$body, 'client_id'=>(string)Str::uuid()]);
            $id = DB::table('task_chat_messages')->where('body',$body)->value('id');
            $job = new \App\Jobs\AnswerTaskQuestion($id);
            $job->handle(app(\App\Services\Tutor\Conversation::class));
            $job->handle(app(\App\Services\Tutor\Conversation::class));
            $answer = DB::table('task_chat_messages')->where('answer_to',$id)->sole();
            $this->assertSame($won, (json_decode($answer->tutor_details, true)['coins'] ?? null) === 1);
            $this->assertSame((int)$won, DB::table('coin_transactions')->where('comment','Tutor question #'.$id)->count());
        }
        $this->assertSame($before+1, (int)User::findOrFail(1)->balance());
    }

    public function test_pilot_only_offers_help_in_selected_courses(): void
    {
        config(['tutor.courses' => ['2']]);
        $this->get('/insider/courses/1/steps/1')->assertOk()->assertDontSee('data-chat-tutor', false);
        $this->postJson(self::URL, $this->question())->assertStatus(503);
        $this->assertSame(0,$this->calls);
        $this->assertDatabaseCount('student_tutor_usage', 0);

        config(['tutor.courses' => ['1']]);
        $this->get('/insider/courses/1/steps/1')->assertOk()->assertSee('data-chat-tutor', false);
    }

    public function test_replies_link_to_the_actual_authorized_block_and_retries_are_deduplicated(): void
    {
        DB::table('program_steps')->where('id', 2)->update(['theory'=>"## Свет\n\nИндекс пикселя определяет его координаты.",'notes'=>'PRIVATE_NOTES']);
        DB::table('program_steps')->where('id', 3)->update(['theory'=>'PRIVATE_LESSON']);
        DB::table('lesson_info')->where(['course_id'=>1,'lesson_id'=>3])->update(['start_date'=>'2099-01-01']);
        DB::table('tasks')->where('id', 11)->update(['solution'=>'PRIVATE_SOLUTION']);
        $step = ProgramStep::find(2);
        $this->stubTutor(function ($request) {
            $payload = $request;
            $this->assertSame('hint', $payload['mode']);
            $this->assertArrayNotHasKey('course_id', $payload);
            $this->assertStringNotContainsString('PRIVATE_', json_encode($payload));
            $ref = collect($payload['readings'])->firstWhere('heading', 'Свет')['id'];
            return ['answer'=>'Проверь координаты.', 'sources'=>[$ref, 'invented'], 'usage'=>self::USAGE];
        });
        $data = $this->question(['task_id'=>11]);
        $result = $this->postJson(self::URL, $data)->assertOk()->assertJsonCount(1, 'links')
            ->assertJsonPath('links.0.heading','Свет');
        $this->assertStringEndsWith('/steps/2#read-'.Reading::revision($step).'-1', $result->json('links.0.url'));
        $this->postJson(self::URL, $data)->assertExactJson($result->json());
        $this->assertDatabaseCount('student_tutor_usage', 1);
        $this->assertDatabaseHas('student_tutor_usage', ['user_id'=>1, 'total_tokens'=>132, 'state'=>'complete']);
        config(['tutor.daily_tokens'=>130]);
        $this->postJson(self::URL, $this->question())->assertStatus(429);
        $this->assertSame(1,$this->calls);
    }

    public function test_uncertain_usage_blocks_only_that_user_for_the_day_and_retries_stay_deduplicated(): void
    {
        config(['tutor.daily_tokens' => 500]);
        $known = ['complete'=>false, 'known_tokens'=>95, 'total_tokens'=>null, 'input_tokens'=>null, 'output_tokens'=>null,
            'calls'=>[['stage'=>'answer','total_tokens'=>95], ['stage'=>'check','total_tokens'=>null]]];
        $this->stubTutor(fn () => ['answer'=>'Проверь пример.', 'sources'=>[], 'usage'=>$this->calls===1 ? $known : self::USAGE]);
        $data = $this->question();
        $this->postJson(self::URL, $data)->assertOk();
        $this->assertDatabaseHas('student_tutor_usage', ['user_id'=>1,'state'=>'unknown','known_tokens'=>95,'total_tokens'=>null]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->postJson(self::URL, $data)->assertStatus(409);
        $this->postJson(self::URL, $this->question())->assertStatus(429);
        $this->actingAs(User::find(3))->postJson(self::URL, $this->question())->assertOk();
        $this->travel(1)->days();
        $this->actingAs(User::find(1))->postJson(self::URL, $this->question())->assertOk();
        $this->assertSame(3,$this->calls);
    }

    public function test_missing_usage_is_a_protocol_error_and_preserves_the_meter_record(): void
    {
        $this->stubTutor(fn () => ['answer'=>'Ответ', 'sources'=>[]]);
        $this->postJson(self::URL, $this->question())->assertStatus(502);
        $this->assertDatabaseHas('student_tutor_usage', ['user_id'=>1, 'state'=>'unknown', 'total_tokens'=>null]);
    }

    public function test_rejects_a_task_from_another_step_and_a_closed_lesson(): void
    {
        $this->postJson(self::URL, $this->question(['task_id'=>21]))->assertNotFound();
        DB::table('lesson_info')->where(['course_id'=>1,'lesson_id'=>1])->update(['start_date'=>'2099-01-01']);
        $this->postJson(self::URL, $this->question())->assertForbidden();
        $this->assertSame(0,$this->calls);
    }

    public function test_full_lesson_assignment_latest_files_and_three_earlier_results_are_assembled_without_an_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('code_checking');
        $full = str_repeat('Учебный пример. ', 220).'КОНЕЦ';
        DB::table('program_steps')->where('id',1)->update(['theory'=>$full]);
        DB::table('program_steps')->insert(['id'=>90,'name'=>'Ноутбук','lesson_id'=>1,'program_id'=>1,'sort_index'=>500,
            'is_notebook'=>true,'theory'=>json_encode(['cells'=>[['cell_type'=>'code','source'=>["x = 42\n"],'outputs'=>[['output_type'=>'stream','text'=>['42']], ['output_type'=>'display_data','data'=>['image/png'=>'PRIVATE_OUTPUT']]]]]])]);
        DB::table('program_steps')->where('id',2)->update(['theory'=>"## Источник\n\nСравни соседние пиксели. ".str_repeat('Детали. ',150).'PRIVATE_TAIL']);
        DB::table('tasks')->where('id',11)->update(['text'=>$full.' Условие целиком']);
        $source = str_repeat("print(1)\n", 600);
        $blob = app(\App\Services\CodeChecking\Artifacts::class)->put($source);
        $lib = app(\App\Services\CodeChecking\Artifacts::class)->put("def helper():\n    pass\n");
        $make = function ($day, $method, $files = [], $user = 1, $task = 11) {
            $id = (string) Str::uuid(); $run = (string) Str::uuid();
            DB::table('code_submissions')->insert(['id'=>$id,'course_id'=>1,'task_id'=>$task,'user_id'=>$user,
                'request_id'=>Str::uuid(),'files'=>json_encode($files),'current_run_id'=>$run,'language'=>'python',
                'payload_hash'=>'h','created_at'=>"2026-11-$day 10:00:00"]);
            DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$id,'state'=>'completed','points'=>4,
                'snapshot'=>json_encode(['check_type'=>$method,'points'=>10,'private'=>'PRIVATE_SNAPSHOT']),
                'comment'=>$method === 'tests' ? 'PRIVATE_TEST_LOG' : 'Проверь границу',
                'criteria'=>json_encode([['title'=>'Граница','points'=>1,'maximum'=>2,'comment'=>'Проверь ноль','secret'=>'PRIVATE_RUBRIC']])]);
            return $run;
        };
        $make(10, 'manual');
        $make(11, 'manual', [['name'=>'PRIVATE_OLD_FILE']]);
        $make(12, 'gpt', [['name'=>'PRIVATE_OLD_FILE']]);
        $run = $make(13, 'tests');
        DB::table('code_test_reports')->insert(['id'=>$run,'user_id'=>1,'course_id'=>1,'task_id'=>11,'started_at'=>0,
            'created_at'=>now(),'code_id'=>'sample','payload_hash'=>'h','report'=>json_encode(['passed'=>0,'total'=>2,'tests'=>[
                ['number'=>1,'verdict'=>'wrong_answer','is_public'=>true,'actual'=>['type'=>'inline','text'=>'4']],
                ['number'=>2,'verdict'=>'runtime_error','is_public'=>false,'expected'=>['type'=>'inline','text'=>'PRIVATE_HIDDEN']],
            ]])]);
        $make(14, 'gpt', [['name'=>'main.py','artifact'=>$blob], ['name'=>'lib.py','artifact'=>$lib],
            ['name'=>'PRIVATE_HELPER','origin'=>'task']]);
        $make(15, 'gpt', [['name'=>'PRIVATE_OTHER_USER']], 3);
        $make(16, 'gpt', [['name'=>'PRIVATE_OTHER_TASK']], 1, 21);
        $this->stubTutor(function ($req) use ($full, $source) {
            $d = $req;
            $this->assertSame(['Ввод и вывод','Ноутбук'],array_column($d['lesson']['steps'],'title'));
            $this->assertSame($full,$d['lesson']['steps'][0]['text']);
            $this->assertStringContainsString("x = 42",$d['lesson']['steps'][1]['text']);
            $this->assertStringContainsString("Вывод ячейки:\n42",$d['lesson']['steps'][1]['text']);
            $this->assertStringContainsString('Сравни соседние пиксели',$d['neighbors'][0]['text']);
            $this->assertStringEndsWith('КОНЕЦ Условие целиком',$d['assignment']);
            $this->assertSame(['main.py','lib.py'],array_column($d['latest_submission']['files'],'name'));
            $this->assertSame($source,$d['latest_submission']['files'][0]['text']);
            $this->assertSame('python',$d['latest_submission']['language']);
            $this->assertSame(['tests','gpt','manual'],array_column($d['previous_results'],'method'));
            $this->assertSame('4',$d['previous_results'][0]['tests'][0]['actual']);
            $this->assertSame(['number'=>2,'verdict'=>'runtime_error'],$d['previous_results'][0]['tests'][1]);
            $this->assertSame('Проверь ноль',$d['previous_results'][1]['criteria'][0]['comment']);
            $this->assertStringNotContainsString('PRIVATE_',json_encode($d,JSON_UNESCAPED_UNICODE));
            return ['answer'=>'Перечитай пример.', 'sources'=>[$d['lesson']['steps'][1]['id']], 'usage'=>self::USAGE];
        });
        $this->postJson(self::URL,$this->question(['task_id'=>11, 'latest_submission'=>['text'=>'PRIVATE_FORGED']]))
            ->assertOk()->assertJsonPath('links.0.title','Ноутбук');
    }

    public function test_older_text_answers_and_a_question_before_any_submission(): void
    {
        $requests = [];
        $this->stubTutor(function ($req) use (&$requests) {
            $requests[] = $req;
            return ['answer'=>'Проверь ещё раз.', 'sources'=>[], 'usage'=>self::USAGE];
        });
        $this->postJson(self::URL,$this->question(['task_id'=>11]))->assertOk();
        $this->postJson(self::URL,$this->question(['task_id'=>11,'intent'=>'submission']))->assertUnprocessable();
        $this->assertDatabaseCount('student_tutor_usage', 1);
        $this->assertNull($requests[0]['latest_submission']);
        $this->assertSame([], $requests[0]['previous_results']);
        DB::table('solutions')->insert(['user_id'=>1,'task_id'=>11,'course_id'=>1,'submitted'=>now(),
            'text'=>"Старый ответ\nстрока 2",'mark'=>null]);
        $this->postJson(self::URL,$this->question(['task_id'=>11]))->assertOk();
        $this->assertSame("Старый ответ\nстрока 2",$requests[1]['latest_submission']['text']);
        $this->postJson(self::URL,$this->question(['task_id'=>11,'intent'=>'hint']))->assertOk();
        $this->postJson(self::URL,$this->question(['task_id'=>11,'intent'=>'submission']))->assertOk();
        $this->assertSame(['hint','submission','hint','submission'], array_column($requests,'mode'));
        $this->assertSame([], $requests[2]['history']);
        $this->assertCount(1, $requests[3]['history']);
    }

    public function test_oversize_input_is_reported_before_a_charge_and_the_assignment_is_never_shortened(): void
    {
        DB::table('tasks')->where('id',11)->update(['text'=>str_repeat('a',\App\Services\Tutor\Context::MAX_BYTES)]);
        $this->postJson(self::URL,$this->question(['task_id'=>11]))->assertStatus(413);
        $this->assertDatabaseCount('student_tutor_usage',0);
        $this->assertSame(0,$this->calls);
    }

    public function test_notebook_reference_uses_original_cell_index_and_excludes_outputs(): void
    {
        $step = new ProgramStep();
        $step->forceFill(['id'=>10,'name'=>'Тетрадь','is_notebook'=>true, 'theory'=>json_encode(['cells'=>[
            ['cell_type'=>'code','source'=>'pass','outputs'=>['private']],
            ['cell_type'=>'markdown','source'=>["## Точность\n","Сравни два значения."]],
        ]])]);
        $parts = Reading::fragments($step);
        $this->assertCount(1, $parts);
        $this->assertSame('read-'.Reading::revision($step).'-1', $parts[0]['anchor']);
        $this->assertStringNotContainsString('private', $parts[0]['text']);
    }

    public function test_a_grade_dispute_raised_in_the_hint_dialog_reviews_the_latest_checked_work(): void
    {
        DB::table('solutions')->insert(['user_id'=>1,'task_id'=>11,'course_id'=>1,'submitted'=>now(),'checked'=>now(),
            'text'=>'Мой ответ','mark'=>5,'comment'=>'Недостаточно пояснений']);
        $this->stubTutor(function ($req) {
            $this->assertSame('submission',$req['mode']);
            $this->assertSame('Мой ответ',$req['latest_submission']['text']);
            return ['answer'=>'Посмотрим на замечание.', 'sources'=>[], 'usage'=>self::USAGE];
        });
        $this->postJson(self::URL,$this->question(['task_id'=>11,'intent'=>'hint','question'=>'Я не согласна с оценкой последней сдачи.']))
            ->assertOk()->assertJsonPath('recheck',null);
        $this->assertSame(1,$this->calls);
    }

    public function test_disputed_check_queues_only_the_selected_work_once_with_its_specific_finding(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['ai.defaults.recheck'=>['model'=>'strong', 'timeout'=>150]]);
        $make = function (string $day, string $code) {
            $submission = (string) Str::uuid();
            $run = (string) Str::uuid();
            DB::table('code_submissions')->insert(['id'=>$submission,'user_id'=>1,'course_id'=>1,'task_id'=>11,
                'request_id'=>Str::uuid(),'payload_hash'=>'h','language'=>'python','files'=>'[]','current_run_id'=>$run,
                'mode'=>'text', 'answer_text'=>$code, 'preparation_state'=>'ready', 'created_at'=>$day]);
            DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$submission,'state'=>'completed',
                'snapshot'=>json_encode(['check_type'=>'gpt','points'=>10,'gpt_model'=>'base','private_instructions'=>'PRIVATE_RULE',
                    'public_text'=>'Исходная формулировка']),
                'comment'=>'Ноль пропущен', 'points'=>7, 'finished_at'=>now()]);
            return [$submission, $run];
        };
        [$id, $run] = $make('2026-11-01', 'if n <= 0:');
        $make('2026-11-02', 'if n < 0:');
        $finding = ['claim'=>'Ноль пропущен','evidence'=>'if n <= 0:', 'focus'=>'Проверить выбор ветки при нуле.'];
        $decision = null;
        $this->stubTutor(function ($req) use (&$decision) {
            $this->assertSame('if n < 0:', $req['latest_submission']['text']);
            $this->assertSame('if n <= 0:', $req['review_basis']['source']['text']);
            $this->assertSame('Исходная формулировка', $req['review_basis']['assignment']);
            $this->assertStringNotContainsString('PRIVATE_RULE', json_encode($req));
            return ['answer'=>'Проверь условие для нуля.', 'sources'=>[], 'recheck'=>$decision, 'guided'=>false, 'usage'=>self::USAGE];
        });
        $context = ['task_id'=>11,'submission_id'=>$id,'run_id'=>$run];
        $this->postJson(self::URL,$this->question($context + ['recheck'=>$finding]))->assertOk()->assertJsonPath('recheck',null);
        $decision = array_replace($finding, ['evidence'=>'if n < 0:']);
        $this->postJson(self::URL,$this->question($context))->assertOk()->assertJsonPath('recheck',null);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $decision = $finding;
        config(['ai.defaults.recheck.model'=>null]);
        $this->postJson(self::URL,$this->question($context))->assertOk()->assertJsonPath('recheck.status','unavailable');
        config(['ai.defaults.recheck.model'=>'strong']);
        $question = $this->question($context);
        $this->postJson(self::URL,$question)->assertOk()->assertJsonPath('recheck.status','queued')->assertJsonPath('recheck.focus',$finding['focus'])->assertJsonPath('recheck.location.line',1);
        $this->postJson(self::URL,$question)->assertOk()->assertJsonPath('recheck.status','queued');
        $this->postJson(self::URL,$this->question($context))->assertOk()->assertJsonPath('recheck.status','already_requested');
        $new = \App\CodeSubmission::findOrFail($id)->currentRun;
        $this->assertSame('strong',$new->snapshot['gpt_model']);
        $this->assertSame($finding,$new->snapshot['ai_review']['finding']);
        $this->assertSame($run,$new->snapshot['ai_review']['source_run_id']);
        $this->assertSame(7, (int) \App\CodeExecutionRun::findOrFail($run)->points);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\DispatchCodeExecution::class,1);
        $this->assertDatabaseCount('student_tutor_usage',5);
    }

    private function chatTurn(string $body, array $metadata = []): array
    {
        $this->postJson('/insider/courses/1/tasks/11/chat/1', $metadata + [
            'body'=>$body, 'client_id'=>(string)Str::uuid(),
        ])->assertOk();
        $job = \Illuminate\Support\Facades\Queue::pushed(\App\Jobs\AnswerTaskQuestion::class)->last();
        $job->handle(app(\App\Services\Tutor\Conversation::class));
        $reply = DB::table('task_chat_messages')->where('answer_to',$job->questionId)->first();
        return ['answer'=>$reply->body] + json_decode($reply->tutor_details, true);
    }

    private function scoredRun(int $points, string $comment, string $code = 'Ответ'): array
    {
        $submission = (string)Str::uuid();
        $run = (string)Str::uuid();
        DB::table('code_submissions')->insert(['id'=>$submission,'user_id'=>1,'course_id'=>1,'task_id'=>11,
            'request_id'=>Str::uuid(),'payload_hash'=>'h','language'=>'python','files'=>'[]','current_run_id'=>$run,
            'mode'=>'text','answer_text'=>$code,'preparation_state'=>'ready','created_at'=>now()]);
        DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$submission,'state'=>'completed',
            'snapshot'=>json_encode(['check_type'=>'gpt','points'=>35]),'comment'=>$comment,'points'=>$points,'finished_at'=>now()]);
        return ['submission_id'=>$submission,'run_id'=>$run];
    }

    public function test_a_named_grade_selects_the_correct_run_and_duplicate_grades_offer_dated_choices(): void
    {
        $old = $this->scoredRun(26,'Разбор старой сдачи','первая версия');
        $new = $this->scoredRun(25,'Разбор новой сдачи','вторая версия');
        $payloads = [];
        $this->stubTutor(function ($data) use (&$payloads) {
            $payloads[] = $data;
            return ['answer'=>'Сравни версии.', 'sources'=>[], 'usage'=>self::USAGE];
        });
        $this->assertSame($old,$this->chatTurn('Почему у меня 26/35 баллов?')['discussed']);
        $this->assertSame('первая версия',$payloads[0]['review_basis']['source']['text']);
        $this->assertSame('Разбор старой сдачи',$payloads[0]['review']['comment']);
        $this->assertNull($payloads[0]['latest_submission']);
        $this->assertSame($old,$this->chatTurn('А почему так?')['discussed']);
        $second = $this->scoredRun(26,'Ещё одна проверка');
        $choices = $this->chatTurn('Почему 26/35?')['choices'];
        $this->assertEqualsCanonicalizing([$old['run_id'],$second['run_id']],array_column($choices,'run_id'));
        $this->assertNotEmpty($choices[0]['at']);
        $this->assertSame($new,$this->chatTurn('Объясни оценку этой проверки.', [
            'attempt'=>$new['submission_id'],'run_id'=>$new['run_id'],
        ])['discussed']);
        $this->assertCount(3,$payloads);
    }

    public function test_followup_checking_a_parameter_keeps_the_previous_step_in_guided_mode(): void
    {
        $source = $this->scoredRun(5, 'Проверь запись файла', 'def write_wav(filename2, values):');
        DB::table('task_chat_messages')->insert([
            'student_id'=>1,'author_id'=>1,'course_id'=>1,'task_id'=>11,'body'=>'Что сравнить?',
            'client_id'=>(string)Str::uuid(),'created_at'=>now(),
        ]);
        $question = DB::table('task_chat_messages')->value('id');
        $step = "Сравни параметр \x60filename2\x60 функции \x60write_wav\x60 с именем в строке записи.";
        DB::table('task_chat_messages')->insert(['student_id'=>1,'author_id'=>1,'course_id'=>1,'task_id'=>11,
            'automatic'=>true,'answer_to'=>$question,'body'=>$step,'tutor_details'=>json_encode(['discussed'=>$source,'next_step'=>$step]),
            'client_id'=>(string)Str::uuid(),'created_at'=>now()]);
        $reply = $this->chatTurn('То есть там должно быть falename1?');
        $this->assertTrue($reply['guided']);
        $this->assertStringContainsString('filename2',$reply['answer']);
        $this->assertStringContainsString('falename1',$reply['answer']);
        $this->assertStringNotContainsString('балл',$reply['answer']);
    }

    public function test_followup_explains_a_completed_tutor_recheck_in_the_same_chat(): void
    {
        $source = $this->scoredRun(5,'Ошибка в обработке канала.');
        $finding = ['claim'=>'Ошибка в обработке канала.', 'evidence'=>'канал', 'focus'=>'Проверить число каналов.'];
        $question = DB::table('task_chat_messages')->insertGetId(['student_id'=>1,'author_id'=>1,'course_id'=>1,'task_id'=>11,
            'body'=>'Проверь замечание.', 'client_id'=>(string)Str::uuid(),'created_at'=>now()]);
        DB::table('task_chat_messages')->insert(['student_id'=>1,'author_id'=>1,'course_id'=>1,'task_id'=>11,
            'automatic'=>true,'answer_to'=>$question,'body'=>'Это замечание спорно.',
            'tutor_details'=>json_encode(['discussed'=>$source,'recheck'=>$finding+['status'=>'queued']]),
            'client_id'=>(string)Str::uuid(),'created_at'=>now()]);
        $newRun = (string)Str::uuid();
        DB::table('code_execution_runs')->insert(['id'=>$newRun,'submission_id'=>$source['submission_id'],'state'=>'completed',
            'points'=>5,'finished_at'=>now(),'comment'=>'Канал читается, входной путь по-прежнему фиксирован.',
            'snapshot'=>json_encode(['check_type'=>'gpt','points'=>35,'ai_review'=>['origin'=>'tutor','source_run_id'=>$source['run_id'],'finding'=>$finding]])]);
        DB::table('code_submissions')->where('id',$source['submission_id'])->update(['current_run_id'=>$newRun]);
        $reply = $this->chatTurn('Почему балл остался тем же?');
        $this->assertStringContainsString('Было 5/35, после перепроверки — 5/35',$reply['answer']);
        $this->assertStringContainsString($finding['focus'],$reply['answer']);
        $this->assertSame($newRun,$reply['discussed']['run_id']);
        $explicit = $this->chatTurn('Что показала перепроверка?', ['attempt'=>$source['submission_id'],'run_id'=>$newRun]);
        $this->assertStringContainsString('Было 5/35, после перепроверки — 5/35', $explicit['answer']);
        $events = $this->getJson('/insider/courses/1/tasks/11/chat/1')->assertOk()->json('events');
        $this->assertSame(5,collect($events)->firstWhere('run',$newRun)['tutor_review']['previous']);
    }

    public function test_examples_are_opened_only_by_a_scoped_button_request_without_model_usage(): void
    {
        (require database_path('migrations/2026_11_22_004000_mark_tutor_test_reveals.php'))->up();
        $target = $this->scoredRun(5,'');
        $id = $target['run_id'];
        DB::table('code_execution_runs')->where('id',$id)->update(['snapshot'=>json_encode(['check_type'=>'tests','points'=>10])]);
        $tests = [];
        foreach (range(1,3) as $number) {
            $tests[] = ['number'=>$number,'verdict'=>'wrong_answer','test_id'=>hash('sha256',(string)$number),'is_public'=>false];
        }
        DB::table('code_test_reports')->insert(['id'=>$id,'code_id'=>$target['submission_id'],'user_id'=>1,'course_id'=>1,'task_id'=>11,
            'started_at'=>1,'payload_hash'=>'h','created_at'=>now(),'report'=>json_encode(['passed'=>0,'total'=>3,'tests'=>$tests])]);
        $this->chatTurn('Что такое тест?');
        $this->assertDatabaseCount('code_test_purchases',0);
        $metadata = ['attempt'=>$target['submission_id'],'run_id'=>$id,'tutor_action'=>'examples'];
        $reply = $this->chatTurn('Покажи бесплатные примеры.', $metadata);
        $this->assertSame([1],array_column($reply['test_examples'],'number'));
        $this->chatTurn('Покажи бесплатные примеры.', $metadata);
        $this->assertDatabaseCount('code_test_purchases',1);
        $this->assertDatabaseCount('student_tutor_usage',1);
    }

    public function test_a_grade_without_feedback_in_normal_chat_never_asks_for_an_absent_comment(): void
    {
        $this->scoredRun(5,'');
        $this->assertStringContainsString('записан только балл',$this->chatTurn('Почему у меня такая оценка?')['answer']);
    }

    public function test_short_confirmation_uses_relevant_readings_without_neighboring_lessons(): void
    {
        $requests = [];
        $this->stubTutor(function ($data) use (&$requests) {
            $requests[] = $data;
            return ['answer'=>'Сравни время начала.', 'next_step'=>'Сравни время начала.', 'sources'=>[], 'usage'=>self::USAGE];
        });
        $this->chatTurn('Как указывать время начала?');
        $this->chatTurn('То есть 25 часов допустимы?');
        $this->assertTrue($requests[1]['followup']);
        $this->assertSame('Сравни время начала.',$requests[1]['dialogue_focus']['proposed_step']);
        $this->assertSame('Как указывать время начала?',$requests[1]['dialogue_focus']['previous_student_words']);
        $this->assertSame([],$requests[1]['neighbors']);
        $this->assertCount(1,$requests[1]['lesson']['steps']);
        $this->assertArrayNotHasKey('previous_results',$requests[1]);
        $this->assertSame('Сравни время начала.',$this->chatTurn('Повтори ещё раз')['answer']);
        $this->assertCount(2,$requests);
    }

    public function test_screen_help_uses_no_model_and_only_handoffs_reach_the_teacher_queue(): void
    {
        $chat = app(\App\Services\TaskChat::class);
        foreach (['dialog'=>'К задаче','panel'=>'встроен'] as $layout=>$hint) {
            $this->assertStringContainsString($hint,$this->chatTurn('Как закрыть чат?', ['chat_layout'=>$layout])['answer']);
        }
        $this->assertDatabaseCount('student_tutor_usage',0);
        $this->assertTrue($chat->waitingStudents(1,[11])->isEmpty());
        config(['tutor.daily_questions'=>0]);
        $this->assertSame('teacher',$this->chatTurn('Почему не получается?')['handoff']);
        $this->assertTrue($chat->waitingStudents(1,[11])->get(11)->has(1));
        $this->actingAs(User::findOrFail(2))->postJson('/insider/courses/1/tasks/11/chat/1', [
            'body'=>'Посмотрим вместе.', 'client_id'=>(string)Str::uuid(),
        ])->assertOk();
        $this->assertTrue($chat->waitingStudents(1,[11])->isEmpty());
    }

    public function test_review_uses_only_the_students_selected_completed_gpt_run(): void
    {
        $submission = (string) Str::uuid(); $run = (string) Str::uuid();
        DB::table('code_submissions')->insert(['id'=>$submission,'user_id'=>1,'course_id'=>1,'task_id'=>11,
            'request_id'=>Str::uuid(),'payload_hash'=>'h','language'=>'python','files'=>'[]','current_run_id'=>$run]);
        DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$submission,'state'=>'completed',
            'snapshot'=>json_encode(['check_type'=>'gpt','points'=>10,'private'=>'PRIVATE_CRITERIA']),
            'comment'=>'Нарушен порядок элементов','criteria'=>json_encode([['title'=>'Порядок','points'=>1,'maximum'=>2,
                'comment'=>'Сравни соседние элементы','internal'=>'PRIVATE_CRITERIA']]),'points'=>7]);
        $this->stubTutor(function ($req) {
            $this->assertSame('review', $req['mode']);
            $this->assertSame("Нарушен порядок элементов\nПорядок 1/2 Сравни соседние элементы", $req['review']['comment']);
            $this->assertStringNotContainsString('PRIVATE_CRITERIA', json_encode($req));
            return ['answer'=>'Сравни два элемента.', 'sources'=>[], 'usage'=>self::USAGE];
        });
        $data = $this->question(['submission_id'=>$submission,'run_id'=>$run,'task_id'=>11]);
        $this->postJson(self::URL, $data)->assertOk();
        $this->actingAs(User::find(3))->postJson(self::URL, $data)->assertNotFound();
        $this->assertSame(1,$this->calls);
    }
}

