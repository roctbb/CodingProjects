<?php

namespace Tests\Feature;

use App\Services\ChatGptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RouterAiTransportTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'ai.provider' => 'openai', 'ai.shared_models' => true,
            'ai.providers.openai' => ['base_url' => 'https://routerai.ru/api/v1/', 'key' => 'private-key', 'proxy' => 'socks5h://proxy.example:1080', 'image_api' => 'images'],
            'ai.defaults.image.model' => 'openai/gpt-image-2', 'ai.defaults.text.model' => 'openai/text-test',
        ]);
        DB::purge('sqlite');
        Http::preventStrayRequests();
        Log::spy();
    }

    public function testTextAndImagesUseOneBaseAndBearerKeyWithoutLegacyEnvelope(): void
    {
        Http::fake([
            'https://routerai.ru/api/v1/responses' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'OK']]]]]),
            'https://routerai.ru/api/v1/images' => Http::response(['data' => [['b64_json' => self::PNG]]]),
        ]);
        $client = app(ChatGptService::class);
        $this->assertSame('OK', $client->generate([['role' => 'user', 'content' => 'Text']], ['command' => 'text_improvement']));
        $image = $client->generateImage([['role' => 'user', 'content' => 'Draw a trophy']], ['command' => 'achievement_image']);
        $this->assertSame(base64_decode(self::PNG), $image['bytes']);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer private-key'));
            $this->assertTrue($request->isJson());
            $this->assertArrayNotHasKey('token', $request->data());
            $this->assertArrayNotHasKey('params', $request->data());
        }
    }

    public function testReferencesAreJsonDataUrlsInOriginalOrderAndKeepTransparencyOptions(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->assertSame('https://routerai.ru/api/v1/images', $request->url());
            $this->assertTrue($request->isJson());
            $this->assertFalse($request->isMultipart());
            $this->assertSame('socks5h://proxy.example:1080', $options['proxy']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertSame(['model' => 'openai/gpt-image-2', 'prompt' => "Keep the face\n\nUse this pose", 'n' => 1,
                'size' => '1024x1536', 'quality' => 'medium', 'output_format' => 'png', 'background' => 'transparent',
                'input_references' => [
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.self::PNG]],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.self::PNG]],
                ],
            ], $request->data());
            return Http::response(['data' => [['b64_json' => self::PNG]]]);
        });
        app(ChatGptService::class)->generateImage([['role' => 'user', 'content' => [
            ['type' => 'input_text', 'text' => 'Keep the face'], ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.self::PNG],
            ['type' => 'input_text', 'text' => 'Use this pose'], ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.self::PNG],
        ]]], ['command' => 'room_character', 'size' => '1024x1536', 'quality' => 'medium', 'output_format' => 'png', 'background' => 'transparent']);
        Http::assertSentCount(1);
    }

    public function testOpenAiAlsoSupportsOneBaseWithoutSeparateImageUrl(): void
    {
        config(['ai.provider' => 'openai', 'ai.providers.openai' => [
            'base_url' => 'https://api.example/custom/v1', 'key' => 'private-key', 'image_api' => 'images',
        ]]);
        Http::fake(['https://api.example/custom/v1/images/generations' => Http::response(['data' => [['b64_json' => self::PNG]]])]);
        app(ChatGptService::class)->generateImage([['role' => 'user', 'content' => 'Draw']], ['command' => 'course_poster']);
        Http::assertSentCount(1);
    }

    public function testRouterAiFailureIsNotRetriedAndDoesNotLeakSensitivePayload(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'model_not_found', 'message' => 'private-key and private prompt']], 400)]);
        try { app(ChatGptService::class)->generateImage([['role' => 'user', 'content' => 'private prompt']]); $this->fail('Expected rejection'); }
        catch (\RuntimeException $e) { $this->assertSame('AI request failed with HTTP 400 (openai)', $e->getMessage()); }
        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $context['error_code'] === 'model_not_found' && !str_contains(json_encode([$message, $context]), 'private'));
    }
}
