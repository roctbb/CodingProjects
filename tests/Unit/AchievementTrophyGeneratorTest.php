<?php

namespace Tests\Unit;

use App\Services\AchievementTrophyGenerator;
use App\Services\ChatGptService;
use ReflectionClass;
use Tests\TestCase;

class AchievementTrophyGeneratorTest extends TestCase
{
    public function testMagentaBackgroundIsConvertedToTransparency()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $source = imagecreatetruecolor(2, 1);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagesetpixel($source, 0, 0, imagecolorallocate($source, 255, 0, 255));
        imagesetpixel($source, 1, 0, imagecolorallocate($source, 220, 180, 40));

        ob_start();
        imagepng($source);
        $bytes = ob_get_clean();
        imagedestroy($source);

        $generator = new AchievementTrophyGenerator(new ChatGptService());
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('removeMagentaBackground');
        $method->setAccessible(true);

        $image = $method->invoke($generator, [
            'bytes' => $bytes,
            'mime' => 'image/png',
            'extension' => 'png',
        ]);

        $result = imagecreatefromstring($image['bytes']);
        $transparentPixel = imagecolorat($result, 0, 0);
        $keptPixel = imagecolorat($result, 1, 0);
        imagedestroy($result);

        $this->assertSame(127, ($transparentPixel >> 24) & 0x7F);
        $this->assertSame(0, ($keptPixel >> 24) & 0x7F);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame('png', $image['extension']);
    }
}
