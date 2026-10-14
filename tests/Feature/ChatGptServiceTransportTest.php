<?php

namespace Tests\Feature;

use App\Services\ChatGptService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChatGptServiceTransportTest extends TestCase
{
    private const GATEWAY = 'https://gateway.example/custom/responses';
    private const OPENAI = 'https://openai.example/v1/responses';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    private const MESSAGES = [['role' => 'user', 'content' => 'private prompt']];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        config([
            'ai.provider' => 'gateway',
            'ai.providers.gateway' => ['endpoint' => self::GATEWAY, 'key' => 'gateway-secret'],
            'ai.providers.openai' => ['endpoint' => self::OPENAI, 'key' => 'openai-secret'],
            'ai.defaults.text' => ['model' => 'text-model', 'timeout' => 90],
            'ai.defaults.image' => ['model' => 'image-responses-model', 'timeout' => 180],
        ]);
        foreach (config('ai.commands') as $name => $command) {
            config(['ai.commands.' . $name . '.provider' => null]);
        }
        Http::preventStrayRequests();
        Log::spy();
    }

    public static function providers(): array
    {
        return [['gateway'], ['openai']];
    }

    private function textResponse(): array
    {
        return ['status' => 'completed', 'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [
                ['type' => 'output_text', 'text' => 'Первая часть.'],
                ['type' => 'output_text', 'text' => 'Вторая часть.'],
            ]],
        ]];
    }

    #[DataProvider('providers')]
    public function testTextUsesSelectedEndpointAndAuthentication(string $provider): void
    {
        config(['ai.provider' => $provider]);
        // Unused credentials are not required, and are never used as a fallback.
        config(['ai.providers.' . ($provider === 'gateway' ? 'openai' : 'gateway') . '.key' => null]);
        $body = $this->textResponse();
        Http::fake(fn () => Http::response($provider === 'gateway' ? ['result' => $body] : $body));

        $text = app(ChatGptService::class)->generate(self::MESSAGES);

        $this->assertSame("Первая часть.\nВторая часть.", $text);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($provider) {
            $this->assertSame('POST', $request->method());
            $this->assertSame($provider === 'gateway' ? self::GATEWAY : self::OPENAI, $request->url());
            $expected = ['model' => 'text-model', 'input' => self::MESSAGES];
            if ($provider === 'gateway') {
                $expected = ['token' => 'gateway-secret'] + $expected;
                $this->assertFalse($request->hasHeader('Authorization'));
            } else {
                $this->assertTrue($request->hasHeader('Authorization', 'Bearer openai-secret'));
            }
            $this->assertEquals($expected, $request->data());
            return true;
        });
    }

    #[DataProvider('providers')]
    public function testImageRequestUsesResponsesToolAndReturnsPng(string $provider): void
    {
        config(['ai.provider' => $provider]);
        $body = ['status' => 'completed', 'output' => [
            ['type' => 'image_generation_call', 'result' => self::PNG],
        ]];
        Http::fake(fn () => Http::response($provider === 'gateway' ? ['result' => $body] : $body));

        $image = app(ChatGptService::class)->generateImage(self::MESSAGES, [
            'size' => '1024x1024', 'quality' => 'medium', 'background' => 'transparent', 'output_format' => 'png',
        ]);

        $this->assertSame(base64_decode(self::PNG), $image['bytes']);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame('png', $image['extension']);
        $this->assertSame('image-responses-model', $image['model']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($provider) {
            $this->assertSame($provider === 'gateway' ? self::GATEWAY : self::OPENAI, $request->url());
            $params = $provider === 'gateway' ? $request['params'] : $request->data();
            $this->assertSame([[
                'type' => 'image_generation', 'size' => '1024x1024', 'quality' => 'medium',
                'output_format' => 'png', 'background' => 'transparent',
            ]], $params['tools']);
            $this->assertSame(['type' => 'image_generation'], $params['tool_choice']);
            if ($provider === 'gateway') {
                $this->assertSame('gateway-secret', $request['token']);
                $this->assertFalse($request->hasHeader('Authorization'));
                $this->assertArrayNotHasKey('tools', $request->data());
            } else {
                $this->assertTrue($request->hasHeader('Authorization', 'Bearer openai-secret'));
                $this->assertArrayNotHasKey('token', $request->data());
                $this->assertArrayNotHasKey('params', $request->data());
            }
            return true;
        });
    }

    public static function commands(): array
    {
        return [
            ['text_improvement', 'text', 60], ['task_summary', 'text', 90],
            ['achievement_variants', 'text', 120], ['achievement_image', 'image', 180],
            ['course_poster', 'image', 180],
            ['room_character', 'image', 180],
        ];
    }

    #[DataProvider('commands')]
    public function testCommandCanOverrideProviderAndModel(string $command, string $type, int $timeout): void
    {
        config(['ai.commands.' . $command . '.provider' => 'openai', 'ai.commands.' . $command . '.model' => 'custom-model']);
        Http::fake(function (Request $request, array $options) use ($timeout, $type) {
            $this->assertSame($timeout, $options['timeout']);
            $this->assertSame(10, $options['connect_timeout']);
            $this->assertFalse($options['allow_redirects']);
            return Http::response($type === 'text' ? $this->textResponse() : [
                'output' => [['type' => 'image_generation_call', 'result' => self::PNG]],
            ]);
        });

        $service = app(ChatGptService::class);
        $type === 'text'
            ? $service->generate(self::MESSAGES, ['command' => $command])
            : $service->generateImage(self::MESSAGES, ['command' => $command]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::OPENAI && $request['model'] === 'custom-model');
    }

    public function testCommandWithoutOverridesInheritsDefaultProviderAndModel(): void
    {
        config(['ai.provider' => 'openai', 'ai.commands.task_summary.model' => null]);
        Http::fake([self::OPENAI => Http::response($this->textResponse())]);
        app(ChatGptService::class)->generate(self::MESSAGES, ['command' => 'task_summary']);
        Http::assertSent(fn (Request $request) => $request['model'] === 'text-model');
    }

    public static function badConfiguration(): array
    {
        return [
            [['ai.provider' => 'typo'], []],
            [['ai.providers.gateway.key' => ''], []],
            [['ai.providers.gateway.endpoint' => 'not-a-url'], []],
            [['ai.providers.gateway.endpoint' => 'file:///tmp/test'], []],
            [['ai.providers.gateway.endpoint' => 'https://user:password@gateway.example/v1/responses'], []],
            [['ai.commands.task_summary.provider' => 'typo'], ['command' => 'task_summary']],
            [['ai.defaults.text.model' => ''], []],
            [[], ['command' => 'unknown']],
            [[], ['command' => 'achievement_image']],
            [[], ['timeout' => 0]],
        ];
    }

    #[DataProvider('badConfiguration')]
    public function testBadConfigurationFailsBeforeSending(array $config, array $options): void
    {
        config($config);
        Http::fake();
        try {
            app(ChatGptService::class)->generate(self::MESSAGES, $options);
            $this->fail('Expected a configuration exception');
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            Http::assertNothingSent();
        }
    }

    public static function badResponses(): array
    {
        return [
            ['openai', ['error' => ['message' => 'openai-secret private prompt']], 401, 'HTTP 401'],
            ['gateway', ['error' => ['message' => 'gateway-secret private prompt']], 429, 'HTTP 429'],
            ['gateway', ['error' => 'private prompt'], 200, 'upstream error'],
            ['gateway', ['result' => ['error' => ['message' => 'private prompt']]], 200, 'upstream error'],
            ['openai', '<html>openai-secret private prompt</html>', 502, 'HTTP 502'],
            ['openai', '<html>private prompt</html>', 200, 'invalid JSON'],
            ['openai', 'null', 200, 'invalid response'],
            ['gateway', ['output' => []], 200, 'invalid response'],
            ['openai', ['status' => 'incomplete', 'output' => []], 200, 'did not complete'],
            ['gateway', ['result' => ['status' => 'failed', 'output' => []]], 200, 'did not complete'],
            ['gateway', [], 307, 'HTTP 307'],
            ['openai', ['output' => 'bad output'], 200, 'invalid output'],
        ];
    }

    #[DataProvider('badResponses')]
    public function testFailuresDoNotRetryOrExposeResponseBodies(string $provider, array|string $body, int $status, string $error): void
    {
        config(['ai.provider' => $provider]);
        Http::fake(fn () => Http::response($body, $status, ['x-request-id' => 'req-test', 'Location' => self::OPENAI]));
        try {
            app(ChatGptService::class)->generate(self::MESSAGES, ['command' => 'task_summary']);
            $this->fail('Expected an AI response exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($error, $e->getMessage());
            $this->assertStringNotContainsString('secret', $e->getMessage());
            $this->assertStringNotContainsString('private prompt', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) use ($provider, $status) {
            $this->assertSame($provider, $context['provider']);
            $this->assertSame('task_summary', $context['command']);
            $this->assertSame($status, $context['status_code']);
            $this->assertSame('req-test', $context['request_id']);
            $this->assertStringNotContainsString('secret', json_encode([$message, $context]));
            $this->assertStringNotContainsString('private prompt', json_encode([$message, $context]));
            return true;
        });
    }

    public function testConnectionFailureDoesNotLeakExceptionOrRetry(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('gateway-secret private prompt');
        });
        try {
            app(ChatGptService::class)->generate(self::MESSAGES);
            $this->fail('Expected connection failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('AI connection failed (gateway)', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertSame(1, $calls);
    }

    public function testImageDownloadDoesNotReceiveApiCredentials(): void
    {
        config(['ai.providers.gateway.proxy' => 'socks5h://user:secret@proxy.example:1080']);
        Http::fake(function (Request $request, array $options) {
            $this->assertSame('socks5h://user:secret@proxy.example:1080', $options['proxy']);
            if ($request->url() === self::GATEWAY) {
                return Http::response(['result' => ['data' => [['url' => 'https://images.example/image.png']]]]);
            }
            $this->assertGreaterThan(0, $options['timeout']);
            $this->assertLessThan(180, $options['timeout']);
            return Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/jpeg']);
        });
        $image = app(ChatGptService::class)->generateImage(self::MESSAGES);
        $this->assertSame('image/png', $image['mime']);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://images.example/image.png'
                && !$request->hasHeader('Authorization') && $request->body() === '';
        });
    }

    public function testImageMimeLabelCannotDisguiseInvalidBytes(): void
    {
        Http::fake([self::GATEWAY => Http::response(['result' => [
            'output' => [['type' => 'image_generation_call', 'result' => 'data:image/png;base64,' . base64_encode('not an image')]],
        ]])]);
        $this->expectExceptionMessage('invalid or unsupported image');
        app(ChatGptService::class)->generateImage(self::MESSAGES);
    }

    public function testTextRefusalIsNotReturnedAsSuccessfulText(): void
    {
        Http::fake([self::GATEWAY => Http::response(['result' => ['output' => [[
            'type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Cannot comply']],
        ]]]])]);
        $this->expectExceptionMessage('AI returned no text');
        app(ChatGptService::class)->generate(self::MESSAGES);
    }
}
