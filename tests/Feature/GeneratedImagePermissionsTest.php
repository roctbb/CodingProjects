<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratedImagePermissionsTest extends TestCase
{
    public function testRepairOnlyChangesGeneratedMediaAndKeepsFileContents(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        Storage::fake('room_character_sources');
        $paths = ['room-characters/user-1-test.png', 'achievement-trophies/achievement-1-test.png', 'course-posters/program-1-test.png'];
        foreach ([...$paths, 'user_avatars/private.png'] as $path) {
            Storage::put($path, 'original image', ['visibility' => 'private', 'directory_visibility' => 'private']);
        }
        Storage::disk('room_character_sources')->put('snapshot.png', 'private avatar', ['visibility' => 'private']);
        $this->artisan('media:repair-generated-permissions --dry-run')->assertSuccessful();
        foreach ($paths as $path) {
            $this->assertSame('private', Storage::getVisibility($path));
            $this->assertSame(0700, fileperms(Storage::path(dirname($path))) & 0777);
        }
        $this->artisan('media:repair-generated-permissions')->assertSuccessful();
        clearstatcache();
        foreach ($paths as $path) {
            $this->assertSame('original image', Storage::get($path));
            $this->assertSame('public', Storage::getVisibility($path));
            $this->assertSame(0755, fileperms(Storage::path(dirname($path))) & 0777);
        }
        $this->assertSame('private', Storage::getVisibility('user_avatars/private.png'));
        $this->assertSame('private', Storage::disk('room_character_sources')->getVisibility('snapshot.png'));
    }
}
