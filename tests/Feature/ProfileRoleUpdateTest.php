<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileRoleUpdateTest extends TestCase
{
    use WithoutMiddleware;

    private $originalDefaultConnection;
    private $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('role')->default('student');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('school')->nullable();
            $table->integer('grade_year')->nullable();
            $table->date('birthday')->nullable();
            $table->boolean('birthday_hidden')->default(false);
            $table->string('gender')->nullable();
            $table->text('hobbies')->nullable();
            $table->text('interests')->nullable();
            $table->string('git')->nullable();
            $table->string('telegram')->nullable();
            $table->text('comments')->nullable();
            $table->string('image')->nullable();
            $table->string('oidc_issuer')->nullable();
            $table->string('oidc_subject')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        DB::disconnect('sqlite');

        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
        ]);

        parent::tearDown();
    }

    public function testAdminCanChangeRoleForAnotherUser(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'student']);
        $this->be($admin);

        $response = $this->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
            'role' => 'teacher',
        ]));

        $response->assertRedirect('/insider/profile/' . $user->id);
        $this->assertSame('teacher', $user->fresh()->role);
    }

    public function testAdminCannotSetUnknownRole(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'student']);
        $this->be($admin);

        $response = $this->from('/insider/profile/' . $user->id . '/edit')
            ->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
                'role' => 'owner',
            ]));

        $response->assertRedirect('/insider/profile/' . $user->id . '/edit');
        $response->assertSessionHasErrors('role');
        $this->assertSame('student', $user->fresh()->role);
    }

    public function testRegularUserCannotChangeTheirOwnRole(): void
    {
        $user = $this->createUser(['role' => 'student']);
        $this->be($user);

        $response = $this->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
            'role' => 'admin',
        ]));

        $response->assertRedirect('/insider/profile/' . $user->id);
        $this->assertSame('student', $user->fresh()->role);
    }

    public function testBirthdayIsRequiredWhenItCanBeEdited(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'student']);
        $this->be($admin);

        $response = $this->from('/insider/profile/' . $user->id . '/edit')
            ->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
                'role' => 'student',
                'birthday' => null,
            ]));

        $response->assertRedirect('/insider/profile/' . $user->id . '/edit');
        $response->assertSessionHasErrors('birthday');
        $this->assertSame('2012-01-01', $user->fresh()->birthday->format('Y-m-d'));
    }

    public function testUserCanHideAndShowTheirBirthday(): void
    {
        $user = $this->createUser();
        $this->be($user);
        Cache::put(User::nearbyBirthdaysCacheKey(), ['stale'], 3600);

        $this->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
            'birthday_hidden' => '1',
        ]))->assertRedirect('/insider/profile/' . $user->id);

        $this->assertTrue($user->fresh()->birthday_hidden);
        $this->assertFalse(Cache::has(User::nearbyBirthdaysCacheKey()));

        $this->post('/insider/profile/' . $user->id . '/edit', $this->profileData([
            'birthday_hidden' => '0',
        ]))->assertRedirect('/insider/profile/' . $user->id);

        $this->assertFalse($user->fresh()->birthday_hidden);
    }

    public function testHiddenBirthdayIsNotPublicOrIncludedInBirthdayQueries(): void
    {
        $visibleUser = $this->createUser();
        $hiddenUser = $this->createUser(['birthday_hidden' => true]);
        $userWithoutBirthday = $this->createUser(['birthday' => null]);

        $this->assertTrue($visibleUser->hasVisibleBirthday());
        $this->assertFalse($hiddenUser->hasVisibleBirthday());
        $this->assertFalse($userWithoutBirthday->hasVisibleBirthday());
        $this->assertSame(
            [$visibleUser->id],
            User::withVisibleBirthday()->orderBy('id')->pluck('id')->all()
        );
    }

    public function testBirthdayIsVisibleByDefault(): void
    {
        $user = $this->createUser();

        $this->assertFalse($user->birthday_hidden);
        $this->assertTrue($user->hasVisibleBirthday());
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated User',
            'school' => 'Силаэдр',
            'grade' => 8,
            'birthday' => '2012-01-01',
            'hobbies' => 'Робототехника',
            'interests' => 'Программирование',
        ], $overrides);
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'User',
            'role' => 'student',
            'email' => uniqid('user_', true) . '@example.test',
            'password' => bcrypt('secret'),
            'school' => 'Силаэдр',
            'grade_year' => now()->year - 8,
            'birthday' => '2012-01-01',
            'hobbies' => 'Робототехника',
            'interests' => 'Программирование',
        ], $overrides));
    }
}
