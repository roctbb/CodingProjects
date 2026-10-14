<?php

namespace Tests\Feature;

use App\Services\ChatGptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImagesApiTransportTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    private const BASE = 'https://images.example/custom/v1/images';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        config([
            'ai.provider' => 'openai',
            'ai.providers.openai' => ['endpoint' => 'https://text.example/v1/responses', 'key' => 'private-key',
                'proxy' => 'socks5h://proxy.example:1080', 'image_api' => 'images', 'images_url' => self::BASE . '/'],
            'ai.commands.room_character.provider' => null, 'ai.commands.room_character.model' => 'gpt-image-2',
            'ai.commands.room_character.timeout' => 180,
            'ai.commands.achievement_image.provider' => null, 'ai.commands.achievement_image.model' => 'gpt-image-2',
        ]);
        Http::preventStrayRequests();
        Log::spy();
    }

    private function messages(): array
    {
        return [['role' => 'user', 'content' => [
            ['type' => 'input_text', 'text' => 'Image 1: face. Image 2: pose. Preserve transparency.'],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,' . self::PNG],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,' . self::PNG],
        ]]];
    }

    public function testCharacterUsesOneMultipartEditWithBothReferencesInOrder(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->assertSame('POST', $request->method());
            $this->assertSame(self::BASE . '/edits', $request->url());
            $this->assertTrue($request->isMultipart());
            $this->assertFalse($request->isJson());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer private-key'));
            $this->assertSame('socks5h://proxy.example:1080', $options['proxy']);
            $this->assertSame(180, $options['timeout']);
            $this->assertFalse($options['allow_redirects']);
            $parts = collect($request->data());
            $files = $parts->where('name', 'image[]')->values();
            $this->assertCount(2, $files);
            foreach ($files as $index => $file) {
                $this->assertSame('reference-' . ($index + 1) . '.png', $file['filename']);
                $this->assertSame(base64_decode(self::PNG), $file['contents']);
                $this->assertSame('image/png', $file['headers']['Content-Type']);
            }
            $fields = $parts->reject(fn ($part) => isset($part['filename']))->pluck('contents', 'name')->all();
            $this->assertEquals([
                'model' => 'gpt-image-2', 'prompt' => $this->messages()[0]['content'][0]['text'], 'n' => 1,
                'size' => '1024x1536', 'quality' => 'medium', 'output_format' => 'png', 'background' => 'transparent',
            ], $fields);
            return Http::response(['data' => [['b64_json' => self::PNG]]]);
        });
        $image = app(ChatGptService::class)->generateImage($this->messages(), [
            'command' => 'room_character', 'size' => '1024x1536', 'quality' => 'medium',
            'background' => 'transparent', 'output_format' => 'png',
        ]);
        $this->assertSame(base64_decode(self::PNG), $image['bytes']);
        $this->assertSame('image/png', $image['mime']);
        Http::assertSentCount(1);
    }

    public function testAchievementUsesOneJsonGenerationAndTextKeepsResponses(): void
    {
        Http::fake([
            self::BASE . '/generations' => Http::response(['data' => [['b64_json' => self::PNG]]]),
            'https://text.example/v1/responses' => Http::response(['output' => [
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Corrected']]],
            ]]),
        ]);
        $service = app(ChatGptService::class);
        $service->generateImage([
            ['role' => 'system', 'content' => 'Create a trophy.'], ['role' => 'user', 'content' => 'A golden key.'],
        ], ['command' => 'achievement_image', 'output_format' => 'png']);
        $this->assertSame('Corrected', $service->generate([['role' => 'user', 'content' => 'Fix typo.']]));
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE . '/generations' && $request->isJson()
                && $request->data() === ['model' => 'gpt-image-2', 'prompt' => "Create a trophy.\n\nA golden key.", 'n' => 1, 'output_format' => 'png'];
        });
    }

    public function testFailureLogsSafeCodeAndRequestIdWithoutRetryOrPrivateData(): void
    {
        Http::fake([self::BASE . '/edits' => Http::response(['error' => [
            'code' => 'invalid_image_input', 'type' => 'neuroapi_error', 'param' => 'image[1]',
            'message' => 'private-key private prompt (request id: req-42)',
        ]], 400)]);
        try {
            app(ChatGptService::class)->generateImage($this->messages(), ['command' => 'room_character']);
            $this->fail('Expected rejection');
        } catch (\RuntimeException $e) {
            $this->assertSame('AI request failed with HTTP 400 (openai)', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
            $this->assertSame('images.edits', $context['api']);
            $this->assertSame('invalid_image_input', $context['error_code']);
            $this->assertSame('neuroapi_error', $context['error_type']);
            $this->assertSame('image[1]', $context['error_param']);
            $this->assertSame('req-42', $context['request_id']);
            $this->assertStringNotContainsString('private', json_encode([$message, $context]));
            return true;
        });
    }

    public static function invalidUrls(): array
    {
        return [[null], ['https://example.com/v1/responses'], ['https://example.com/v1/images/edits'],
            ['file:///tmp/images'], ['https://user:pass@example.com/images'], ['https://example.com/images?key=secret']];
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidImagesUrlNeverFallsBackToAnotherEndpoint(?string $url): void
    {
        config(['ai.providers.openai.images_url' => $url]);
        Http::fake();
        try {
            app(ChatGptService::class)->generateImage($this->messages(), ['command' => 'room_character']);
            $this->fail('Expected validation failure');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('AI Images URL', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function testInvalidReferenceIsRejectedBeforeSendingAnyPaidRequest(): void
    {
        $messages = $this->messages();
        $messages[0]['content'][2]['image_url'] = 'data:image/png;base64,' . base64_encode('not an image');
        Http::fake();
        try {
            app(ChatGptService::class)->generateImage($messages, ['command' => 'room_character']);
            $this->fail('Expected invalid reference');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Invalid Images API reference', $e->getMessage());
        }
        Http::assertNothingSent();
    }
}
