<?php

namespace Tests\Unit;

use App\Services\ChatGptService;
use Tests\TestCase;

class ChatGptServiceImageTest extends TestCase
{
    public function testResponseMetadataUrlsAreNotTreatedAsGeneratedImages(): void
    {
        $this->assertSame([], ChatGptService::imageFromResponsePayload([
            'metadata' => ['url' => 'https://example.com/private'],
            'output' => [['type' => 'message', 'content' => [[
                'type' => 'output_text', 'text' => 'No image',
                'annotations' => [['url' => 'https://example.com/source']],
            ]]]],
        ]));
    }

    public function testImagePayloadCanBeExtractedFromResponsesImageGenerationResult()
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $payload = [
            'result' => [
                'output' => [
                    [
                        'type' => 'image_generation_call',
                        'result' => base64_encode($png),
                    ],
                ],
            ],
        ];

        $image = ChatGptService::imageFromResponsePayload($payload);

        $this->assertSame($png, $image['bytes']);
        $this->assertArrayNotHasKey('url', $image);
    }

    public function testImagePayloadCanBeExtractedFromDataUrl()
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $payload = [
            'result' => [
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'data:image/png;base64,' . base64_encode($png),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $image = ChatGptService::imageFromResponsePayload($payload);

        $this->assertSame($png, $image['bytes']);
        $this->assertSame('image/png', $image['mime']);
    }
}
