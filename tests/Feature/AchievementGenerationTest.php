<?php

namespace Tests\Feature;

use App\Achievement;
use App\CourseActivity;
use App\Http\Controllers\GeekPasteAPI;
use App\Http\Controllers\TasksController;
use App\Jobs\GenerateAchievementTrophy;
use App\Jobs\GenerateSolutionAchievement;
use App\Services\AchievementTrophyGenerator;
use App\Services\ChatGptService;
use App\Services\GeekPasteClient;
use App\Services\SolutionAchievementGenerator;
use App\Solution;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AchievementGenerationTest extends TestCase
{
    private const URL = '/insider/courses/7/tasks/12/solution/1';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'queue.default' => 'database']);
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats BOOLEAN DEFAULT 0);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, text TEXT, max_mark INTEGER, is_code BOOLEAN DEFAULT 0, generates_ai_achievement BOOLEAN DEFAULT 1);
            CREATE TABLE task_deadlines (id INTEGER PRIMARY KEY, task_id INTEGER, course_id INTEGER, deadline DATETIME);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, lesson_id INTEGER);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, task_id INTEGER, course_id INTEGER, user_id INTEGER, text TEXT, mark INTEGER, raw_mark INTEGER, submitted DATETIME);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT, created_at DATETIME, updated_at DATETIME);
            CREATE TABLE course_activities (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, step_id INTEGER, task_id INTEGER, solution_id INTEGER, user_id INTEGER, type TEXT, payload TEXT, created_at DATETIME, updated_at DATETIME);
            CREATE TABLE jobs (id INTEGER PRIMARY KEY, queue TEXT, payload TEXT, attempts INTEGER, reserved_at INTEGER, available_at INTEGER, created_at INTEGER);');
        require_once database_path('migrations/2026_05_10_170000_create_achievements_table.php');
        (new \CreateAchievementsTable())->up();
        (require database_path('migrations/2026_10_14_120000_remove_legacy_achievement_visuals.php'))->up();
        DB::table('users')->insert([
            ['id' => 42, 'name' => 'Student', 'email' => 'student@example.test', 'role' => 'student'],
            ['id' => 99, 'name' => 'Teacher', 'email' => 'teacher@example.test', 'role' => 'teacher'],
        ]);
        DB::table('courses')->insert(['id' => 7, 'name' => 'Programming']);
        DB::table('course_teachers')->insert(['course_id' => 7, 'user_id' => 99]);
        DB::table('tasks')->insert(['id' => 12, 'name' => 'Language', 'text' => 'Build an interpreter', 'max_mark' => 10]);
        DB::table('solutions')->insert(['id' => 1, 'task_id' => 12, 'course_id' => 7, 'user_id' => 42, 'text' => 'A stack based interpreter', 'mark' => 10, 'raw_mark' => 10, 'submitted' => now()]);
        $this->actingAs(User::findOrFail(99));
        Notification::fake();
        Storage::fake();
        $this->instance(ChatGptService::class, Mockery::mock(ChatGptService::class));
        $this->instance(GeekPasteClient::class, Mockery::mock(GeekPasteClient::class));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function variants(): array
    {
        return ['variants' => [
            ['title' => 'Хранитель стека', 'description' => 'За язык со стековой машиной.'],
            ['title' => 'Повелитель команд', 'description' => 'За создание собственного интерпретатора.'],
            ['title' => 'Архитектор языка', 'description' => 'За исполнение программ с помощью стека.'],
        ]];
    }

    private function expectTextRequest(string $response): void
    {
        $this->app->make(ChatGptService::class)->shouldReceive('generate')->once()
            ->withArgs(function ($messages, $options) {
                $this->assertSame('achievement_variants', $options['command']);
                $this->assertStringNotContainsString('svg', strtolower($messages[0]['content']));
                $this->assertStringContainsString('ровно 3', $messages[0]['content']);
                return true;
            })->andReturn($response);
    }

    private function award(): Achievement
    {
        return $this->app->make(SolutionAchievementGenerator::class)->createForSolution(
            Solution::findOrFail(1), $this->variants()['variants'][1] + ['coin_reward' => 15], true
        );
    }

    public function testRealServicesMakeExactlyTwoAiRequestsWithMixedProviders(): void
    {
        $this->app->forgetInstance(ChatGptService::class);
        Queue::fake();
        config([
            'ai.provider' => 'gateway',
            'ai.providers.gateway' => ['key' => 'gateway-key', 'endpoint' => 'https://gateway.example/v1/responses'],
            'ai.providers.openai' => ['key' => 'openai-key', 'endpoint' => 'https://openai.example/v1/responses'],
            'ai.commands.achievement_variants.provider' => null,
            'ai.commands.achievement_image.provider' => 'openai',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/v1/responses' => Http::response(['result' => ['output' => [[
                'type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->variants())]],
            ]]]]),
            'https://openai.example/v1/responses' => Http::response(['output' => [[
                'type' => 'image_generation_call', 'result' => self::PNG,
            ]]]),
        ]);

        $this->post(self::URL . '/achievement-preview')->assertRedirect();
        $this->assertSame($this->variants(), session('achievement_previews.1'));
        $this->post(self::URL . '/achievement', $this->variants()['variants'][0])->assertRedirect();
        Queue::pushed(GenerateAchievementTrophy::class)->first()->handle(app(AchievementTrophyGenerator::class));

        Http::assertSentCount(2);
        $requests = Http::recorded()->pluck(0);
        $this->assertSame('https://gateway.example/v1/responses', $requests[0]->url());
        $this->assertSame('gateway-key', $requests[0]['token']);
        $this->assertSame('https://openai.example/v1/responses', $requests[1]->url());
        $this->assertTrue($requests[1]->hasHeader('Authorization', 'Bearer openai-key'));
        $this->assertSame(['type' => 'image_generation'], $requests[1]['tool_choice']);
        $this->assertSame('png', $requests[1]['tools'][0]['output_format']);
        Storage::assertExists(Achievement::firstOrFail()->payload['trophy_image']);
    }

    public function testManualFlowMakesOneTextRequestThenOneQueuedPngRequest(): void
    {
        Queue::fake();
        $this->expectTextRequest(json_encode($this->variants(), JSON_UNESCAPED_UNICODE));
        $this->post(self::URL . '/achievement-preview')->assertRedirect(self::URL . '/achievement-preview');
        $this->assertSame($this->variants(), session('achievement_previews.1'));
        Queue::assertNothingPushed();

        // The real template renders the three choices without icon controls or SVG fields.
        $view = app(TasksController::class)->showSolutionAchievementPreview(7, 12, 1);
        Auth::logout();
        $html = $view->render();
        $this->assertSame(3, substr_count($html, 'class="solution-achievement-variant"'));
        $this->assertStringNotContainsString('name="svg_icon"', $html);
        $this->assertStringNotContainsString('name="icon_key"', $html);
        $this->assertStringNotContainsString('name="visual_key"', $html);
        $this->actingAs(User::findOrFail(99));

        $choice = $this->variants()['variants'][1] + ['coin_reward' => 15];
        $this->post(self::URL . '/achievement', $choice)->assertRedirect();
        $achievement = Achievement::firstOrFail();
        $this->assertSame($choice['title'], $achievement->title);
        $this->assertSame(['coin_reward' => 15, 'manual' => true], $achievement->payload);
        $this->assertNull(session('achievement_previews.1'));
        $this->assertSame(15, DB::table('coin_transactions')->sum('price'));
        Queue::assertPushed(GenerateAchievementTrophy::class, 1);

        $this->app->make(ChatGptService::class)->shouldReceive('generateImage')->once()
            ->withArgs(function ($messages, $options) use ($choice) {
                $this->assertStringContainsString($choice['title'], $messages[1]['content']);
                $this->assertSame('png', $options['output_format']);
                $this->assertSame('transparent', $options['background']);
                $this->assertSame('achievement_image', $options['command']);
                return true;
            })->andReturn(['bytes' => base64_decode(self::PNG)]);
        $job = Queue::pushed(GenerateAchievementTrophy::class)->first();
        $job->handle(app(AchievementTrophyGenerator::class));
        $job->handle(app(AchievementTrophyGenerator::class));
        $this->post(self::URL . '/achievement', $choice)->assertRedirect();
        Storage::assertExists($achievement->fresh()->payload['trophy_image']);
        $this->assertSame(1, Achievement::count());
        $this->assertSame(1, CourseActivity::count());
        $this->assertSame(1, DB::table('coin_transactions')->count());
        Queue::assertPushed(GenerateAchievementTrophy::class, 1);
    }

    public function testAutomaticGenerationIsIdempotentAndDoesNotAwardManualCoins(): void
    {
        Queue::fake();
        $this->expectTextRequest(json_encode($this->variants()));
        $job = new GenerateSolutionAchievement(1);
        $job->handle(app(SolutionAchievementGenerator::class));
        $job->handle(app(SolutionAchievementGenerator::class));
        $achievement = Achievement::firstOrFail();
        $this->assertSame($this->variants()['variants'][0]['title'], $achievement->title);
        $this->assertSame(['coin_reward' => 0, 'manual' => false], $achievement->payload);
        $this->assertSame(0, DB::table('coin_transactions')->count());
        Queue::assertPushed(GenerateAchievementTrophy::class, 1);
    }

    #[DataProvider('invalidResponses')]
    public function testInvalidAiResponsesDoNotPublishAnAchievement(string $response): void
    {
        Queue::fake();
        $this->expectTextRequest($response);
        $this->post(self::URL . '/achievement-preview')->assertRedirect()->assertSessionHas('alert-class', 'alert-danger');
        $this->assertSame(0, Achievement::count());
        $this->assertNull(session('achievement_previews.1'));
        Queue::assertNothingPushed();
    }

    public static function invalidResponses(): array
    {
        $valid = ['title' => 'Title', 'description' => 'Description'];
        return [
            'empty object' => ['{}'],
            'invalid json' => ['not json'],
            'too few variants' => [json_encode(['variants' => [$valid]])],
            'duplicate titles' => [json_encode(['variants' => [$valid, $valid, $valid]])],
            'empty text' => [json_encode(['variants' => [$valid, ['title' => 'Other', 'description' => ' '], ['title' => 'Third', 'description' => 'Text']]])],
            'non string text' => [json_encode(['variants' => [$valid, ['title' => [], 'description' => 'Text'], ['title' => 'Third', 'description' => 'Text']]])],
        ];
    }

    public function testInvalidAwardFormDoesNotStartAnotherGeneration(): void
    {
        $this->postJson(self::URL . '/achievement', ['title' => 'Title'])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertSame(0, Achievement::count());
    }

    #[DataProvider('unavailableCode')]
    public function testCodeLookupNeverFallsBackToAnotherAttempt(?array $response): void
    {
        DB::table('tasks')->update(['is_code' => true]);
        DB::table('solutions')->update(['text' => 'https://paste.geekclass.ru/?id=wanted123']);
        $this->app->make(GeekPasteClient::class)->shouldReceive('solution')->with('wanted123')->once()->andReturn($response);
        $this->expectException(\RuntimeException::class);
        app(SolutionAchievementGenerator::class)->previewForSolution(Solution::findOrFail(1));
    }

    public static function unavailableCode(): array
    {
        return [
            'unavailable' => [null],
            'other code' => [['id' => 'different456', 'raw_code' => 'different code']],
            'other student' => [['id' => 'wanted123', 'user_id' => 43, 'raw_code' => 'different code']],
            'empty code' => [['id' => 'wanted123', 'raw_code' => '']],
        ];
    }

    public function testExactCodeIsIncludedInTheTextRequest(): void
    {
        DB::table('tasks')->update(['is_code' => true]);
        DB::table('solutions')->update(['text' => 'https://paste.geekclass.ru/?id=wanted123']);
        $this->app->make(GeekPasteClient::class)->shouldReceive('solution')->with('wanted123')->once()->andReturn([
            'solution' => ['id' => 'wanted123', 'task_id' => 12, 'course_id' => 7, 'user_id' => 42, 'raw_code' => 'print("specific attempt")', 'lang' => 'python'],
        ]);
        $this->app->make(ChatGptService::class)->shouldReceive('generate')->once()->withArgs(function ($messages) {
            $this->assertStringContainsString('print("specific attempt")', $messages[1]['content']);
            return true;
        })->andReturn(json_encode($this->variants()));
        $this->assertSame($this->variants(), app(SolutionAchievementGenerator::class)->previewForSolution(Solution::findOrFail(1)));
    }

    public function testDatabaseFailureRollsBackTheAwardAndCoinsInsteadOfBecomingADuplicate(): void
    {
        Queue::fake();
        Schema::drop('course_activities');
        try {
            $this->award();
            $this->fail('The failed write must propagate');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(0, Achievement::count());
            $this->assertSame(0, DB::table('coin_transactions')->count());
            Queue::assertNothingPushed();
            Notification::assertNothingSent();
        }
    }

    public function testEditsUpdateOnePulseEntryAndQueueRegeneration(): void
    {
        Queue::fake();
        $achievement = $this->award();
        $this->actingAs(User::findOrFail(99)->forceFill(['role' => 'admin']));
        $this->post('/insider/profile/42/achievement/' . $achievement->id, [
            'title' => 'New title', 'description' => 'New description', 'regenerate_trophy' => 1,
        ])->assertRedirect();
        $this->assertSame(1, CourseActivity::count());
        $this->assertSame('New title', CourseActivity::first()->payload['achievement_title']);
        $this->assertSame('New description', CourseActivity::first()->payload['achievement_description']);
        $this->assertArrayNotHasKey('svg_icon', CourseActivity::first()->payload);
        $this->assertArrayNotHasKey('trophy_image', CourseActivity::first()->payload);
        Queue::assertPushed(GenerateAchievementTrophy::class, 2);
    }

    public function testAnotherTeacherCannotGenerateOrAwardAchievements(): void
    {
        $this->actingAs((new User())->forceFill(['id' => 100, 'role' => 'teacher']));
        $this->post(self::URL . '/achievement-preview')->assertForbidden();
        $this->post(self::URL . '/achievement', $this->variants()['variants'][0])->assertForbidden();
    }

    public function testImageFailureKeepsTheAwardAndCanBeRetriedWithoutRegeneratingText(): void
    {
        Queue::fake();
        $achievement = $this->award();
        $job = new GenerateAchievementTrophy($achievement->id);
        $chat = app(ChatGptService::class);
        $chat->shouldReceive('generateImage')->once()->ordered()->andThrow(new \RuntimeException('Gateway unavailable'));
        $chat->shouldReceive('generateImage')->once()->ordered()->andReturn(['bytes' => base64_decode(self::PNG)]);
        try {
            $job->handle(app(AchievementTrophyGenerator::class));
            $this->fail('The job must report failure to the worker');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gateway unavailable', $e->getMessage());
        }
        $this->assertNull($achievement->fresh()->trophyImageUrl());
        $job->handle(app(AchievementTrophyGenerator::class));
        Storage::assertExists($achievement->fresh()->payload['trophy_image']);
        $this->assertSame(1, CourseActivity::count());
        $this->assertSame(15, DB::table('coin_transactions')->sum('price'));
    }

    public function testRegenerationReplacesTheFileWithoutOverwritingConcurrentEdits(): void
    {
        Queue::fake();
        $achievement = $this->award();
        $oldPath = 'achievement-trophies/achievement-' . $achievement->id . '-old.png';
        Storage::put($oldPath, base64_decode(self::PNG));
        $achievement->payload = $achievement->payload + ['trophy_image' => $oldPath];
        $achievement->save();
        app(ChatGptService::class)->shouldReceive('generateImage')->once()->andReturnUsing(function () use ($achievement) {
            $edited = $achievement->fresh();
            $edited->title = 'Edited while generating';
            $edited->payload = $edited->payload + ['other_setting' => true];
            $edited->save();
            return ['bytes' => base64_decode(self::PNG)];
        });
        (new GenerateAchievementTrophy($achievement->id, true))->handle(app(AchievementTrophyGenerator::class));
        $current = $achievement->fresh();
        $this->assertSame('Edited while generating', $current->title);
        $this->assertTrue($current->payload['other_setting']);
        Storage::assertExists($current->payload['trophy_image']);
        Storage::assertMissing($oldPath);
    }

    public function testAiJobsUseAnAsyncConnectionEvenWithASyncDefault(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertSame('database', (new GenerateSolutionAchievement(1))->connection);
        $this->assertSame('database', (new GenerateAchievementTrophy(1))->connection);
    }

    public function testBothAutomaticEntryPointsPersistJobsBeforeTheResponseEnds(): void
    {
        foreach ([TasksController::class, GeekPasteAPI::class] as $controller) {
            $method = new \ReflectionMethod($controller, 'dispatchAiAchievementGeneration');
            $method->invoke(app($controller), Solution::findOrFail(1));
        }
        $this->assertSame(2, DB::table('jobs')->count());
        foreach (['database', 'redis', 'beanstalkd'] as $driver) {
            $this->assertGreaterThan((new GenerateAchievementTrophy(1))->timeout, config('queue.connections.' . $driver . '.retry_after'));
        }
    }
}
