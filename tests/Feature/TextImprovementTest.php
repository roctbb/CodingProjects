<?php

namespace Tests\Feature;

use App\Console\Commands\FixTyposCommand;
use App\Lesson;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TextImprovementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutMiddleware();
        config([
            'ai.provider' => 'gateway',
            'ai.providers.openai' => ['key' => 'test-key', 'endpoint' => 'https://api.example/v1/responses'],
            'ai.commands.text_improvement.provider' => 'openai',
            'ai.commands.text_improvement.model' => 'editor-model',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://api.example/v1/responses' => Http::response(['output' => [[
            'type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Исправленный **текст**.']],
        ]]])]);
    }

    public static function editorActions(): array
    {
        return [
            ['/insider/chatgpt/improve-text', 'fix_typos', 'Исправь орфографические'],
            ['/insider/chatgpt/improve-text', 'improve_style', 'Улучши стиль'],
            ['/insider/chatgpt/improve-text', 'both', 'Исправь орфографические'],
            ['/insider/yandexgpt/improve-text', 'fix_typos', 'Исправь орфографические'],
        ];
    }

    #[DataProvider('editorActions')]
    public function testEditorUsesSharedResponsesTransport(string $url, string $action, string $instruction): void
    {
        $this->postJson($url, ['text' => 'Текст с ашибками', 'action' => $action])
            ->assertOk()->assertJson(['success' => true, 'improved_text' => 'Исправленный **текст**.']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($instruction) {
            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'editor-model'
                && str_contains($request['input'][0]['content'], $instruction)
                && $request['input'][1]['content'] === 'Текст с ашибками';
        });
    }

    public function testInvalidEditorActionDoesNotCallAi(): void
    {
        $this->postJson('/insider/chatgpt/improve-text', ['text' => 'Текст с ашибками', 'action' => 'unknown'])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function testConsoleCorrectionUsesTheSameTransport(): void
    {
        $lesson = Mockery::mock(Lesson::class)->makePartial();
        $lesson->setRawAttributes(['id' => 1, 'name' => 'Текст с ашибками']);
        $lesson->shouldReceive('save')->once()->andReturnTrue();

        $method = new \ReflectionMethod(FixTyposCommand::class, 'correctTextField');
        $method->invoke(app(FixTyposCommand::class), $lesson, 'name');

        $this->assertSame('Исправленный **текст**.', $lesson->name);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['model'] === 'editor-model'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }
}
