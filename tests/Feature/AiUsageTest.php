<?php

namespace Tests\Feature;

use App\Services\Ai\ApiClient;
use App\Services\ChatGptService;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    private const URL = '/insider/settings/ai/usage';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'ai.provider' => 'openai', 'ai.shared_models' => true,
            'ai.defaults.text.model' => 'configured-model', 'ai.defaults.image.model' => 'image-model',
            'ai.providers.openai' => ['key' => 'private-key', 'endpoint' => 'https://usage.test/v1/responses',
                'image_api' => 'images', 'images_url' => 'https://usage.test/v1/images'],
        ]);
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT)');
        DB::unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        (require database_path('migrations/2026_11_21_230000_create_student_tutor_usage.php'))->up();
        Http::preventStrayRequests();
    }

    private function migrateUsage(): void
    {
        (require database_path('migrations/2026_11_22_005000_create_ai_token_usage.php'))->up();
    }

    private function login(string $role, int $id): void
    {
        $user = (new User())->forceFill(['id' => $id, 'name' => 'Администратор', 'role' => $role, 'email_verified_at' => now()]);
        $user->setRelation('courses', collect())->setRelation('managed_courses', collect());
        $this->actingAs($user);
    }

    public function test_generation_records_returned_model_usage_and_the_requesting_user(): void
    {
        $this->migrateUsage();
        $this->login('teacher', 42);
        $messages = [['role' => 'user', 'content' => 'Текст задания']];
        Http::fake([
            'https://usage.test/v1/responses' => Http::response(['model' => 'vendor/model-v2',
                'usage' => ['input_tokens' => 340, 'output_tokens' => 25],
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Ответ']]]]]),
            'https://usage.test/v1/images/generations' => Http::response(['usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2],
                'data' => [['b64_json' => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7']]]),
            'https://usage.test/v1/responses/input_tokens' => Http::response(['input_tokens' => 300]),
            'https://usage.test/v1/failure' => Http::response(['error' => ['type' => 'overloaded']], 503),
        ]);

        $service = app(ChatGptService::class);
        $this->assertSame('Ответ', $service->generate($messages, ['command' => 'text_improvement']));
        try {
            $service->generateImage($messages, ['command' => 'achievement_image', 'usage_user_id' => 11]);
            $this->fail('Expected the image to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('AI returned an invalid or unsupported image', $e->getMessage());
        }
        $api = app(ApiClient::class);
        $api->create(['model' => 'configured-model'], 'openai', ['key' => 'private-key', 'endpoint' => 'https://usage.test/v1/responses/input_tokens'],
            10, 'student_tutor', api: 'input_tokens', userId: 11);
        try {
            $api->create(['model' => 'configured-model'], 'openai', ['key' => 'private-key', 'endpoint' => 'https://usage.test/v1/failure'],
                10, 'student_tutor', userId: 11);
            $this->fail('Expected the upstream error.');
        } catch (\RuntimeException $e) {
            $this->assertSame('AI request failed with HTTP 503 (openai)', $e->getMessage());
        }

        $this->assertDatabaseHas('ai_token_usage', ['purpose' => 'text_improvement', 'model' => 'vendor/model-v2',
            'user_id' => 42, 'total_tokens' => 365]);
        $this->assertDatabaseHas('ai_token_usage', ['purpose' => 'achievement_image', 'model' => 'image-model',
            'user_id' => 11, 'total_tokens' => 12]);
        $this->assertDatabaseHas('ai_token_usage', ['purpose' => 'student_tutor', 'model' => 'configured-model',
            'user_id' => 11, 'total_tokens' => null, 'known_tokens' => null]);
        $this->assertDatabaseCount('ai_token_usage', 3);
    }

    public function test_report_imports_tutor_history_and_filters_all_breakdowns(): void
    {
        DB::table('users')->insert([['id' => 11, 'name' => 'Тестовый ученик'], ['id' => 12, 'name' => 'Другой ученик']]);
        $history = [
            ['model' => 'tutor-model', 'input_tokens' => 1100, 'output_tokens' => 40, 'total_tokens' => 1140],
            ['model' => 'check-model', 'input_tokens' => 50, 'total_tokens' => null],
        ];
        DB::table('student_tutor_usage')->insert(['user_id' => 11, 'request_id' => (string) Str::uuid(),
            'usage_day' => today(), 'state' => 'unknown', 'known_tokens' => 1190, 'created_at' => now(),
            'usage' => json_encode(['calls' => $history], JSON_THROW_ON_ERROR)]);
        $this->migrateUsage();
        DB::table('ai_token_usage')->insert(['purpose' => 'code_check', 'model' => 'tutor-model', 'user_id' => 12,
            'total_tokens' => 5000, 'known_tokens' => 5000, 'created_at' => now()->subDays(10)]);

        $this->get(self::URL)->assertRedirect('/login');
        $this->login('teacher', 12);
        $this->get(self::URL)->assertForbidden();
        $this->login('admin', 42);
        $this->get(self::URL)->assertOk()->assertSee('Расход токенов ИИ')->assertSee('6 190')
            ->assertSee('Тестовый ученик')->assertSee('Другой ученик')->assertSee('Школьный помощник')->assertSee('check-model');
        $this->get(self::URL.'?period=7&user=11&purpose=student_tutor&model=tutor-model')->assertOk()
            ->assertSee('1 140')->assertSee('Тестовый ученик')->assertDontSee('Другой ученик')->assertDontSee('5 000')
            ->assertViewHas('totals', fn ($totals) => (int) $totals->calls === 1 && (int) $totals->calls_without_total === 0);
        $this->get(self::URL.'?model=check-model')->assertOk()->assertSee('Вызовы без полного счётчика токенов — 1')
            ->assertViewHas('totals', fn ($totals) => (int) $totals->tokens === 50);
        $this->get(self::URL.'?period=7&user=12')->assertOk()->assertSee('Запросов пока нет');
        $this->assertDatabaseCount('ai_token_usage', 3);
    }
}
