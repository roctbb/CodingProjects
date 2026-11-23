<?php

namespace Tests\Feature;

use App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemoteAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.jwt_secret' => str_repeat('test', 16), 'filesystems.default' => 'local']);
        config(['auth.sso_return_origins' => ['https://exam.example.test']]);
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
        $this->assertSame(120, $claims->exp - $claims->iat);
        $this->assertSame('https://exam.example.test', $claims->service_origin);
        $this->assertObjectNotHasProperty('aud', $claims);
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
        ]))->assertRedirectContains('/login?service_login=');
        $this->assertGuest();
    }

    public function test_password_login_returns_to_the_service_that_opened_this_form(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->boolean('birthday_hidden')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
        });
        $credentials = ['email' => 'pupil@example.test', 'password' => 'local-test-password'];
        (new User())->forceFill(['email' => $credentials['email'], 'password' => bcrypt($credentials['password'])])->save();

        $issuer = route('service.jwt', ['redirect_url' => 'https://exam.example.test/auth/callback?state=first']);
        $loginPage = $this->get($issuer)->assertRedirect()->headers->get('Location');
        $this->get($loginPage)->assertOk()->assertSee('action="'.e($loginPage).'"', false);
        $this->get('/insider/jwt?'.http_build_query(['redirect_url' => 'https://exam.example.test/auth/callback?state=second']));
        $this->get('/insider/courses')->assertRedirect('/login');
        $this->post($loginPage, [...$credentials, 'password' => 'incorrect'])->assertRedirect($loginPage);
        $this->assertGuest();
        $this->post($loginPage, $credentials)->assertRedirect($issuer);
        $this->assertAuthenticated();
        $this->get($loginPage)->assertRedirect($issuer);
    }

    public function test_an_unrelated_encrypted_value_is_not_a_service_continuation(): void
    {
        $user = (new User())->forceFill(['id' => 42, 'role' => 'student']);
        $this->actingAs($user)->get(route('login', ['service_login' => Crypt::encryptString('unrelated')]))
            ->assertRedirect('/home');
    }

    public function test_untrusted_origins_credentials_ports_and_fragments_cannot_receive_a_token(): void
    {
        $user = (new User())->forceFill(['id'=>42,'name'=>'Student','role'=>'student','email_verified_at'=>now()]);
        $this->actingAs($user);
        foreach (['https://evil.test/auth', 'https://exam.example.test.evil.test/auth', 'http://evil.test/auth',
            'https://exam.example.test:444/auth', 'http://exam.example.test:444/auth'] as $url) {
            $this->get('/insider/jwt?'.http_build_query(['redirect_url'=>$url]))->assertForbidden();
        }
        foreach (['https://name:secret@exam.example.test/auth', 'https://exam.example.test/auth#token=old'] as $url) {
            $this->get('/insider/jwt?'.http_build_query(['redirect_url'=>$url]))->assertStatus(422);
        }
    }

    public function test_proxy_generated_http_callbacks_upgrade_only_to_registered_https_origins(): void
    {
        $user=(new User())->forceFill(['id'=>42,'name'=>'Student','role'=>'student','email_verified_at'=>now()]);
        foreach (['http://exam.example.test/auth/callback?next=%2Fjoin','http://exam.example.test:80/auth/callback?next=%2Fjoin','https://EXAM.example.test:443/auth/callback?next=%2Fjoin'] as $url) {
            $response=$this->actingAs($user)->get('/insider/jwt?'.http_build_query(['redirect_url'=>$url]));
            $response->assertStatus(302)->assertHeader('Referrer-Policy','no-referrer');
            $target=$response->headers->get('Location');
            $this->assertSame('https',parse_url($target,PHP_URL_SCHEME));
            $this->assertSame('exam.example.test',parse_url($target,PHP_URL_HOST));
            $this->assertNull(parse_url($target,PHP_URL_PORT));
            parse_str(parse_url($target,PHP_URL_QUERY),$query);
            $this->assertSame('/join',$query['next']);
            $claims=JWT::decode($query['token'],new Key(config('auth.jwt_secret'),'HS256'));
            $this->assertSame('https://exam.example.test',$claims->service_origin);
            $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        }
    }

    public function test_explicit_local_development_origin_remains_supported(): void
    {
        config(['auth.sso_return_origins'=>['http://localhost:5173']]);
        $user=(new User())->forceFill(['id'=>42,'name'=>'Student','role'=>'student','email_verified_at'=>now()]);
        $response=$this->actingAs($user)->get('/insider/jwt?'.http_build_query(['redirect_url'=>'http://localhost:5173/auth/callback']));
        $response->assertStatus(302);
        $this->assertStringStartsWith('http://localhost:5173/auth/callback?',$response->headers->get('Location'));
    }
}
