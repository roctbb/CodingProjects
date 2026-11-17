<?php

namespace Tests\Feature;

use App\Services\GuardIdentitySync;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GuardIdentitySyncTest extends TestCase
{
    public function test_sync_uses_an_independent_short_lived_token_and_only_identity_fields(): void
    {
        config(['guard.url' => 'https://guard.geekclass.ru', 'guard.secret' => str_repeat('guard-test-', 6)]);
        Http::preventStrayRequests();
        Http::fake(['https://guard.geekclass.ru/api/v1/internal/identities' => Http::response(['ok' => true])]);
        app(GuardIdentitySync::class)->send([['id' => 42, 'name' => 'Учитель', 'role' => 'teacher', 'email' => 'private@example.test', 'password' => 'private']]);
        Http::assertSent(function ($request) {
            $token = substr($request->header('Authorization')[0], 7);
            $claims = JWT::decode($token, new Key(config('guard.secret'), 'HS256'));
            $this->assertSame('codingprojects', $claims->sub);
            $this->assertSame('guard:identities', $claims->aud);
            $this->assertSame(30, $claims->exp - $claims->iat);
            $this->assertSame(['users' => [['id' => 42, 'name' => 'Учитель', 'role' => 'teacher']]], $request->data());
            return true;
        });
    }

    public function test_disabled_integration_makes_no_requests(): void
    {
        config(['guard.url' => '', 'guard.secret' => '']);
        Http::fake();
        app(GuardIdentitySync::class)->send([['id' => 42, 'name' => 'Student', 'role' => 'student']]);
        Http::assertNothingSent();
    }

    public function test_redirects_and_errors_are_not_accepted_as_sync_success(): void
    {
        config(['guard.url' => 'https://guard.geekclass.ru', 'guard.secret' => str_repeat('guard-test-', 6)]);
        Http::fake(['*' => Http::response('Private response must not appear in exception', 302, ['Location' => 'https://evil.test'])]);
        $this->expectExceptionMessage('Guard identity sync HTTP 302');
        app(GuardIdentitySync::class)->send([['id' => 42, 'name' => 'Student', 'role' => 'student']]);
    }

    public function test_insecure_remote_destination_is_rejected_before_transmission(): void
    {
        config(['guard.url' => 'http://guard.geekclass.ru', 'guard.secret' => str_repeat('guard-test-', 6)]);
        Http::fake();
        try {
            app(GuardIdentitySync::class)->send([['id' => 42, 'name' => 'Student', 'role' => 'student']]);
            $this->fail('Insecure destination accepted');
        } catch (RuntimeException $exception) {
            Http::assertNothingSent();
        }
    }

    public function test_novice_has_only_student_permissions(): void
    {
        config(['guard.url' => 'https://guard.geekclass.ru', 'guard.secret' => str_repeat('guard-test-', 6)]);
        Http::fake(['*' => Http::response(['ok' => true])]);
        app(GuardIdentitySync::class)->send([['id' => 42, 'name' => 'Novice', 'role' => 'novice']]);
        Http::assertSent(fn ($request) => $request->data()['users'][0]['role'] === 'student');
    }

    public function test_legacy_names_cannot_poison_a_whole_sync_batch(): void
    {
        config(['guard.url' => 'https://guard.geekclass.ru', 'guard.secret' => str_repeat('guard-test-', 6)]);
        Http::fake(['*' => Http::response(['ok' => true])]);
        app(GuardIdentitySync::class)->send([
            ['id' => 42, 'name' => "\n\x00", 'role' => 'student'],
            ['id' => 43, 'name' => str_repeat('Я', 180), 'role' => 'student'],
        ]);
        Http::assertSent(fn ($request) => $request->data()['users'][0]['name'] === 'Пользователь #42'
            && mb_strlen($request->data()['users'][1]['name']) === 160);
    }
}
