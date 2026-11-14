<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AvatarPresentationTest extends TestCase
{
    private function framedUser(string $role = 'student'): User
    {
        return (new User())->forceFill([
            'name' => 'Участник клуба',
            'role' => $role,
            'avatar_frame' => 'custom',
            'avatar_frame_expires_at' => now()->addDay(),
            'avatar_frame_config' => User::customAvatarFrameDefaults(),
        ]);
    }

    public function test_working_avatar_can_omit_purchased_frame_and_effects(): void
    {
        $user = $this->framedUser();
        $html = Blade::render('<x-gc-avatar :user="$user" src="/images/photo.png" :frame="false" size="md" alt="Участник" />', compact('user'));

        $this->assertStringContainsString('gc-avatar-frame--none', $html);
        $this->assertStringContainsString('src="/images/photo.png"', $html);
        $this->assertStringContainsString('alt="Участник"', $html);
        $this->assertStringNotContainsString('gc-avatar-frame__effect', $html);
        $this->assertStringNotContainsString('gc-avatar-frame__avatar-effect', $html);
        $this->assertStringNotContainsString('--profile-avatar-frame-animation', $html);
        $this->assertSame('custom', $user->avatar_frame);
    }

    public function test_public_avatar_keeps_purchased_frame_regardless_of_role(): void
    {
        foreach (['student', 'teacher', 'admin'] as $role) {
            $user = $this->framedUser($role);
            $html = Blade::render('<x-gc-avatar :user="$user" src="/images/photo.png" size="md" />', compact('user'));
            $this->assertStringContainsString('gc-avatar-frame--custom', $html);
            $this->assertStringContainsString('gc-avatar-frame__effect', $html);
            $this->assertStringContainsString('--profile-avatar-frame-bg', $html);
        }
    }
}
