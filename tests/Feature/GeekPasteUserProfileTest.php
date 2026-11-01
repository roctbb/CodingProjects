<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeekPasteUserProfileTest extends TestCase
{
    private const SECRET = 'testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttest';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'auth.jwt_secret' => self::SECRET]);
        DB::purge('sqlite');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
        });
        DB::table('users')->insert(['id' => 42, 'name' => 'Анна Иванова',
            'email' => 'private@example.test', 'password' => 'private-password']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function token(array $claims = [], ?string $secret = null): string
    {
        return JWT::encode(array_merge([
            'purpose' => 'user-profile', 'aud' => 'codingprojects',
            'iat' => time(), 'exp' => time() + 60, 'user_id' => 42,
        ], $claims), $secret ?? self::SECRET, 'HS256');
    }

    public function testReturnsOnlyIdAndNameWithoutRequiringAnySubmissions(): void
    {
        $this->getJson('/api/geekpaste/users/42', ['Authorization' => 'Bearer ' . $this->token()])
            ->assertOk()->assertExactJson(['state' => 'ok', 'user' => ['id' => 42, 'name' => 'Анна Иванова']]);
    }

    public function testUnknownUserReturnsNotFound(): void
    {
        $this->getJson('/api/geekpaste/users/99', ['Authorization' => 'Bearer ' . $this->token(['user_id' => 99])])
            ->assertNotFound()->assertExactJson(['state' => 'not_found']);
    }

    public function testRequiresSignedTokenWithMatchingPurposeAudienceLifetimeAndUserId(): void
    {
        $this->getJson('/api/geekpaste/users/42')->assertUnauthorized();
        $this->getJson('/api/geekpaste/users/42', ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
        $this->getJson('/api/geekpaste/users/42', ['Authorization' => 'Bearer ' . $this->token([], str_repeat('wrong', 16))])
            ->assertUnauthorized();
        foreach ([['purpose' => 'login'], ['aud' => 'other'], ['user_id' => 99],
            ['iat' => time() - 120, 'exp' => time() - 60], ['exp' => time() + 600],
            ['exp' => null], ['iat' => null]] as $claims) {
            $this->getJson('/api/geekpaste/users/42', ['Authorization' => 'Bearer ' . $this->token($claims)])
                ->assertUnauthorized();
        }
    }
}
