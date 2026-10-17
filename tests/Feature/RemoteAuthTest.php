<?php

namespace Tests\Feature;

use App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemoteAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.jwt_secret' => str_repeat('test', 16), 'filesystems.default' => 'local']);
        Storage::fake('local');
    }

    private function claims(?string $image): object
    {
        $user = (new User())->forceFill([
            'id' => 42,
            'name' => 'Ученик',
            'role' => 'student',
            'email_verified_at' => now(),
            'image' => $image,
        ]);
        $response = $this->actingAs($user)->get('/insider/jwt?' . http_build_query([
            'redirect_url' => 'https://exam.example.test/auth/callback?next=%2Fjoin',
        ]));
        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertSame('exam.example.test', parse_url($location, PHP_URL_HOST));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('/join', $query['next']);

        return JWT::decode($query['token'], new Key(config('auth.jwt_secret'), 'HS256'));
    }

    public function testLoginTokenIncludesVersionedAbsoluteAvatarUrl(): void
    {
        Storage::disk('local')->put('user_avatars/42.jpg', 'avatar');
        $version = Storage::disk('local')->lastModified('user_avatars/42.jpg');

        $claims = $this->claims('user_avatars/42.jpg');

        $this->assertSame(42, $claims->id);
        $this->assertSame('Ученик', $claims->name);
        $this->assertSame('student', $claims->role);
        $this->assertSame(url('/media/user_avatars/42.jpg') . '?v=' . $version, $claims->avatar_url);
        $this->assertGreaterThanOrEqual(time() - 5, $claims->iat);
    }

    public function testMissingPhotoUsesTheSameDefaultAvatarAsTheProfile(): void
    {
        foreach ([null, 'user_avatars/missing.jpg'] as $image) {
            $this->assertSame(url('/images/user.jpg'), $this->claims($image)->avatar_url);
        }
    }

    public function testGuestsCannotObtainALoginToken(): void
    {
        $this->get('/insider/jwt?' . http_build_query([
            'redirect_url' => 'https://exam.example.test/auth/callback',
        ]))->assertRedirect('/login');
    }
}
