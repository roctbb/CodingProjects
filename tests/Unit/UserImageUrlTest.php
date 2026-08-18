<?php

namespace Tests\Unit;

use App\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImageUrlTest extends TestCase
{
    public function testAvatarUrlContainsFileVersion(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('user_avatars/1.jpg', 'avatar');

        $user = new User();
        $user->image = 'user_avatars/1.jpg';
        $version = Storage::disk('local')->lastModified($user->image);

        $this->assertSame(
            url('/media/user_avatars/1.jpg') . '?v=' . $version,
            $user->imageUrl()
        );
    }
}
