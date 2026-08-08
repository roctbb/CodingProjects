<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\SilaederOidcController;
use App\Notifications\ConfirmSilaederOidcLink;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SilaederOidcAuthenticationTest extends TestCase
{
    private const ISSUER = 'https://lk.example.test';
    private const CLIENT_ID = 'coding-projects-test';
    private const CLIENT_SECRET = 'test-client-secret';

    private $originalDefaultConnection;
    private $originalSqliteDatabase;
    private string $privateKey;
    private array $jwks;
    private ?string $idToken = null;
    private array $userinfo = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'app.enable_email_verification' => false,
            'services.silaeder_oidc' => [
                'enabled' => true,
                'issuer' => self::ISSUER,
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'redirect_uri' => 'https://app.example.test/auth/silaeder/callback',
                'post_logout_redirect_uri' => 'https://app.example.test/auth/silaeder/logout/callback',
            ],
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('role')->default('student');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->string('oidc_issuer')->nullable();
            $table->string('oidc_subject')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['oidc_issuer', 'oidc_subject']);
        });

        Schema::create('oidc_link_requests', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->string('oidc_issuer');
            $table->string('oidc_subject');
            $table->string('name');
            $table->string('email');
            $table->string('role');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        $this->createSigningKey();
        $this->fakeProvider();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('oidc_link_requests');
        Schema::dropIfExists('users');
        DB::disconnect('sqlite');

        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
        ]);

        parent::tearDown();
    }

    public function testStudentCanSignInThroughSilaederOidc(): void
    {
        [$query, $flow] = $this->startLogin();

        $this->idToken = $this->makeIdToken('student-subject', $flow['nonce']);
        $this->userinfo = [
            'sub' => 'student-subject',
            'name' => 'Иван Ученик',
            'preferred_username' => 'student@example.test',
            'email' => 'student@example.test',
            'email_verified' => false,
            'role' => 'student',
            'roles' => ['student'],
        ];

        $response = $this->get('/auth/silaeder/callback?' . http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]));

        $response->assertRedirect('/insider/courses');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Иван Ученик',
            'email' => 'student@example.test',
            'role' => 'student',
            'oidc_issuer' => self::ISSUER,
            'oidc_subject' => 'student-subject',
        ]);
        $this->assertNotNull(User::first()->email_verified_at);
        $this->assertTrue((bool) session(SilaederOidcController::AUTHENTICATED_SESSION_KEY));

        Http::assertSent(function (HttpRequest $request) use ($flow) {
            return $request->url() === self::ISSUER . '/api/oauth/token'
                && $request['code_verifier'] === $flow['code_verifier']
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode(
                    self::CLIENT_ID . ':' . self::CLIENT_SECRET
                ));
        });
        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === self::ISSUER . '/api/oauth/userinfo'
                && $request->hasHeader('Authorization', 'Bearer access-token');
        });
    }

    public function testAdminRoleSignsInAsTeacher(): void
    {
        [$query, $flow] = $this->startLogin();

        $this->idToken = $this->makeIdToken('admin-subject', $flow['nonce']);
        $this->userinfo = [
            'sub' => 'admin-subject',
            'name' => 'Администратор',
            'email' => 'admin@example.test',
            'email_verified' => true,
            'role' => 'admin',
            'roles' => ['admin'],
        ];

        $response = $this->get('/auth/silaeder/callback?' . http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]));

        $response->assertRedirect('/insider/courses');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'role' => 'teacher',
            'oidc_subject' => 'admin-subject',
        ]);
    }

    public function testCallbackRejectsInvalidStateBeforeExchangingCode(): void
    {
        $this->startLogin();

        $response = $this->get('/auth/silaeder/callback?code=authorization-code&state=wrong-state');

        $response->assertRedirect('/login');
        $response->assertSessionHas('oidc_error');
        $this->assertGuest();
        Http::assertNotSent(fn (HttpRequest $request) => $request->url() === self::ISSUER . '/api/oauth/token');
    }

    public function testCallbackRejectsIdTokenWithWrongNonce(): void
    {
        [$query] = $this->startLogin();
        $this->idToken = $this->makeIdToken('student-subject', 'wrong-nonce');
        $this->userinfo = [
            'sub' => 'student-subject',
            'name' => 'Иван Ученик',
            'email' => 'student@example.test',
            'email_verified' => true,
            'role' => 'student',
            'roles' => ['student'],
        ];

        $response = $this->get('/auth/silaeder/callback?' . http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]));

        $response->assertRedirect('/login');
        $response->assertSessionHas('oidc_error', 'Не удалось проверить ответ ЛК Силаэдра. Начните вход заново.');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Http::assertNotSent(fn (HttpRequest $request) => $request->url() === self::ISSUER . '/api/oauth/userinfo');
    }

    public function testExistingEmailIsLinkedOnlyAfterEmailConfirmation(): void
    {
        Notification::fake();
        $existingUser = User::create([
            'name' => 'Существующий пользователь',
            'role' => 'student',
            'email' => 'student@example.test',
            'password' => bcrypt('password'),
        ]);
        [$query, $flow] = $this->startLogin();

        $this->idToken = $this->makeIdToken('different-subject', $flow['nonce']);
        $this->userinfo = [
            'sub' => 'different-subject',
            'name' => 'Иван Ученик',
            'email' => 'student@example.test',
            'email_verified' => true,
            'role' => 'admin',
            'roles' => ['admin'],
        ];

        $response = $this->get('/auth/silaeder/callback?' . http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]));

        $response->assertRedirect('/login');
        $response->assertSessionHas(
            'oidc_status',
            'Аккаунт с таким email уже существует. Мы отправили ссылку для подтверждения и привязки.'
        );
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(User::first()->oidc_subject);
        $this->assertDatabaseCount('oidc_link_requests', 1);

        $confirmationUrl = null;
        Notification::assertSentTo(
            $existingUser,
            ConfirmSilaederOidcLink::class,
            function (ConfirmSilaederOidcLink $notification) use (&$confirmationUrl) {
                $confirmationUrl = $notification->confirmationUrl;
                return true;
            }
        );
        $this->assertNotNull($confirmationUrl);

        $confirmation = $this->get($confirmationUrl);

        $confirmation->assertRedirect('/insider/courses');
        $this->assertAuthenticatedAs($existingUser);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('oidc_link_requests', 0);
        $existingUser->refresh();
        $this->assertSame('different-subject', $existingUser->oidc_subject);
        $this->assertSame(self::ISSUER, $existingUser->oidc_issuer);
        $this->assertSame('teacher', $existingUser->role);
        $this->assertNotNull($existingUser->email_verified_at);
    }

    public function testExistingUserCanLinkMatchingSilaederAccount(): void
    {
        $user = User::create([
            'name' => 'Старое имя',
            'role' => 'teacher',
            'email' => 'teacher@example.test',
            'password' => bcrypt('password'),
        ]);
        $this->be($user);

        [$query, $flow] = $this->startLogin('/auth/silaeder/link');
        $this->idToken = $this->makeIdToken('teacher-subject', $flow['nonce']);
        $this->userinfo = [
            'sub' => 'teacher-subject',
            'name' => 'Учитель Силаэдра',
            'email' => 'teacher@example.test',
            'email_verified' => true,
            'role' => 'teacher',
            'roles' => ['teacher'],
        ];

        $response = $this->get('/auth/silaeder/callback?' . http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]));

        $response->assertRedirect('/insider/profile/' . $user->id . '/edit');
        $response->assertSessionHas('status', 'Аккаунт ЛК Силаэдра привязан.');
        $this->assertSame('teacher-subject', $user->fresh()->oidc_subject);
        $this->assertSame('Учитель Силаэдра', $user->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function testOidcLoginUsesProviderLogoutAndValidatesReturnedState(): void
    {
        $user = User::create([
            'name' => 'Иван Ученик',
            'role' => 'student',
            'email' => 'student@example.test',
            'password' => bcrypt('password'),
        ]);
        $user->forceFill([
            'oidc_issuer' => self::ISSUER,
            'oidc_subject' => 'student-subject',
        ])->save();
        $this->be($user);

        $response = $this->withSession([
            SilaederOidcController::AUTHENTICATED_SESSION_KEY => true,
        ])->post('/logout');

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith(self::ISSUER . '/oauth/logout?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(
            'https://app.example.test/auth/silaeder/logout/callback',
            $query['post_logout_redirect_uri']
        );
        $this->assertGuest();

        $callback = $this->get('/auth/silaeder/logout/callback?' . http_build_query([
            'state' => $query['state'],
        ]));
        $callback->assertRedirect('/login');
        $callback->assertSessionHas('oidc_status', 'Вы вышли из приложения и ЛК Силаэдра.');
    }

    private function startLogin(string $path = '/auth/silaeder'): array
    {
        $response = $this->get($path);
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith(self::ISSUER . '/oauth/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $flow = session(SilaederOidcController::LOGIN_FLOW_SESSION_KEY);

        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(hash('sha256', $query['state']), $flow['state_hash']);
        $this->assertSame(
            $this->base64UrlEncode(hash('sha256', $flow['code_verifier'], true)),
            $query['code_challenge']
        );

        return [$query, $flow];
    }

    private function fakeProvider(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if ($request->url() === self::ISSUER . '/.well-known/openid-configuration') {
                return Http::response($this->discovery());
            }
            if ($request->url() === self::ISSUER . '/api/oauth/token') {
                return Http::response([
                    'access_token' => 'access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 900,
                    'id_token' => $this->idToken,
                ]);
            }
            if ($request->url() === self::ISSUER . '/api/oauth/jwks') {
                return Http::response($this->jwks);
            }
            if ($request->url() === self::ISSUER . '/api/oauth/userinfo') {
                return Http::response($this->userinfo);
            }

            return Http::response([], 404);
        });
    }

    private function discovery(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER . '/oauth/authorize',
            'token_endpoint' => self::ISSUER . '/api/oauth/token',
            'userinfo_endpoint' => self::ISSUER . '/api/oauth/userinfo',
            'jwks_uri' => self::ISSUER . '/api/oauth/jwks',
            'end_session_endpoint' => self::ISSUER . '/oauth/logout',
            'id_token_signing_alg_values_supported' => ['RS256'],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }

    private function makeIdToken(string $subject, string $nonce): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::ISSUER,
            'sub' => $subject,
            'aud' => [self::CLIENT_ID],
            'iat' => $now,
            'exp' => $now + 900,
            'nonce' => $nonce,
        ], $this->privateKey, 'RS256', 'test-key');
    }

    private function createSigningKey(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKey);
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($key);

        $this->jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ]],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
