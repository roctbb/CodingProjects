<?php

namespace Tests\Unit;

use App\Achievement;
use App\Services\AchievementTrophyGenerator;
use App\Services\ChatGptService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AchievementTrophyGeneratorTest extends TestCase
{
    #[DataProvider('invalidImages')]
    public function testRejectsInvalidPngBeforeSaving(array $image): void
    {
        $achievement = new Achievement(['title' => 'Title', 'description' => 'Description']);
        $achievement->setRelation('course', null)->setRelation('task', null);
        $chat = Mockery::mock(ChatGptService::class);
        $chat->shouldReceive('generateImage')->once()->andReturn($image);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid PNG');
        (new AchievementTrophyGenerator($chat))->generateForAchievement($achievement);
    }

    public static function invalidImages(): array
    {
        return [
            'empty' => [[]],
            'text labeled PNG' => [['bytes' => 'not an image', 'mime' => 'image/png']],
            'GIF labeled PNG' => [['bytes' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'mime' => 'image/png']],
        ];
    }
}
