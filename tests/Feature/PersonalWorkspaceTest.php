<?php

namespace Tests\Feature;

use App\Rank;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonalWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('student');
            $table->boolean('birthday_hidden')->default(false);
            $table->string('telegram_chat_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        DB::unprepared("CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, title TEXT)");
        DB::unprepared('CREATE TABLE ranks (id INTEGER PRIMARY KEY, name TEXT, "from" INTEGER, "to" INTEGER)');
        DB::unprepared('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT)');
        DB::unprepared('CREATE TABLE course_students (course_id INTEGER, user_id INTEGER)');
        DB::unprepared('CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER)');
        Schema::create('coin_transactions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->integer('price');
            $table->string('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('market_goods', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('number')->default(1);
            $table->integer('price')->default(10);
            $table->boolean('in_stock')->default(false);
            $table->string('sale_type')->default('regular');
        });
        Schema::create('market_deals', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('good_id');
            $table->integer('price')->nullable();
            $table->boolean('shipped')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testOptionalAboutFieldsCanStayEmptyWhenUpdatingTheProfile(): void
    {
        $this->addPersonalColumns();
        $user = $this->user();
        $this->actingAs($user)->post('/insider/profile/'.$user->id.'/edit', [
            'name' => 'Updated name', 'school' => 'School', 'grade' => 8,
            'hobbies' => '', 'interests' => '', 'birthday_hidden' => '0', 'comments' => 'Cannot set private comment',
        ])->assertRedirect('/insider/profile/'.$user->id)->assertSessionHasNoErrors();
        $this->assertSame('Updated name', $user->fresh()->name);
        $this->assertNull($user->fresh()->hobbies);
        $this->assertNull($user->fresh()->interests);
        $this->assertNull($user->fresh()->comments);
    }

    public function testAdminCanSaveTheTeacherCommentShownInTheEditor(): void
    {
        $this->addPersonalColumns();
        $user = $this->user();
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/insider/profile/'.$user->id.'/edit', [
            'name'=>'Student', 'school'=>'School', 'grade'=>8, 'role'=>'student', 'birthday'=>'2011-02-03',
            'comments'=>'Private teacher note',
        ])->assertRedirect('/insider/profile/'.$user->id)->assertSessionHasNoErrors();
        $this->assertSame('Private teacher note',$user->fresh()->comments);
    }

    public function testPasswordFormOnlyUpdatesPassword(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/insider/profile/'.$user->id.'/password', [
            'password' => 'new-secret', 'password_confirmation' => 'new-secret',
            'name' => 'Injected name', 'role' => 'admin',
        ])->assertRedirect('/insider/profile/'.$user->id.'/edit?section=account')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame('student', $user->fresh()->role);
    }

    public function testInvalidPasswordDoesNotChangeAccount(): void
    {
        $user = $this->user();
        foreach ([['short', 'short'], ['new-secret', 'different']] as [$password, $confirmation]) {
            $this->actingAs($user)->from('/insider/profile/'.$user->id.'/edit?section=account')
                ->post('/insider/profile/'.$user->id.'/password', ['password' => $password, 'password_confirmation' => $confirmation])
                ->assertSessionHasErrors('password');
            $this->assertTrue(Hash::check('old-secret', $user->fresh()->password));
        }
    }

    public function testPasswordAndAccountSettingsKeepOwnerAndAdminAccess(): void
    {
        $target = $this->user();
        $url = '/insider/profile/'.$target->id;
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs($this->user($role))->get($url.'/edit?section=account')->assertForbidden();
            $this->post($url.'/password', ['password' => 'new-secret', 'password_confirmation' => 'new-secret'])->assertForbidden();
        }
        $this->assertTrue(Hash::check('old-secret', $target->fresh()->password));
        $this->actingAs($this->user('admin'))->post($url.'/password', ['password' => 'new-secret', 'password_confirmation' => 'new-secret'])->assertRedirect();
        $this->assertTrue(Hash::check('new-secret', $target->fresh()->password));
    }

    public function testGuestsMustLogInToChangePassword(): void
    {
        $this->post('/insider/profile/1/password')->assertRedirect('/login');
    }

    public function testTelegramConnectionLivesInOwnerAccountSettings(): void
    {
        config(['services.telegram.bot_username' => 'school_test_bot']);
        $owner = $this->user();
        $owner->forceFill(['telegram_chat_id' => '123456789'])->save();
        $this->actingAs($owner)->get('/insider/profile/'.$owner->id.'/edit?section=account')
            ->assertOk()->assertSee('/'.$owner->id.'/telegram-link', false)
            ->assertSee('/'.$owner->id.'/telegram-unlink', false)->assertDontSee('123456789');
        config(['services.telegram.bot_username' => '']);
        $this->get('/insider/profile/'.$owner->id.'/edit?section=account')->assertOk()
            ->assertDontSee('/'.$owner->id.'/telegram-link', false)
            ->assertSee('/'.$owner->id.'/telegram-unlink', false);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/insider/profile/'.$owner->id.'/edit?section=account')
            ->assertOk()->assertDontSee('/'.$owner->id.'/telegram-link', false)
            ->assertDontSee('/'.$owner->id.'/telegram-unlink', false)->assertDontSee('123456789');
    }

    public function testProfileKeepsRoomButDoesNotDuplicateShopOrTelegramSettings(): void
    {
        $user = $this->user();
        $user->setComputedScore(20);
        $user->setComputedRank((new Rank())->forceFill(['name' => 'Новичок', 'from' => 0, 'to' => 100]));
        $this->actingAs($user);
        $html = view('profile.details', [
            'user' => $user, 'guest' => $user, 'coinBalance' => 50, 'errors' => new \Illuminate\Support\ViewErrorBag(),
            'stickers' => collect(), 'sticker_description' => [], 'coinTransactions' => collect(),
            'canViewMoneyHistory' => true, 'canManageMoney' => false, 'achievements' => collect(),
        ])->render();
        $this->assertStringContainsString('data-learning-avatar-form', $html);
        $this->assertStringContainsString('id="room-settings"', $html);
        $this->assertStringContainsString('/insider/market#market-history', $html);
        $this->assertStringNotContainsString('/telegram-link', $html);
        $this->assertStringNotContainsString('name="custom_title"', $html);
        $this->assertStringNotContainsString('name="avatar_frame"', $html);
    }

    public function testTeacherCommentRemainsSeparateAndUnavailableToStudentVisitors(): void
    {
        $owner = $this->user();
        $owner->forceFill(['comments' => 'Private teacher observation', 'interests' => 'Public interests']);
        $owner->setComputedScore(20);
        $owner->setComputedRank((new Rank())->forceFill(['name' => 'Новичок', 'from' => 0, 'to' => 100]));
        foreach (['student', 'teacher', 'admin'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer);
            $html = view('profile.details', [
                'user' => $owner, 'guest' => $viewer, 'coinBalance' => 0,
                'errors' => new \Illuminate\Support\ViewErrorBag(), 'stickers' => collect(),
                'sticker_description' => [], 'coinTransactions' => collect(),
                'canViewMoneyHistory' => false, 'canManageMoney' => false, 'achievements' => collect(),
            ])->render();
            $this->assertStringContainsString('Public interests', $html);
            if ($role === 'student') {
                $this->assertStringNotContainsString('Private teacher observation', $html);
            } else {
                $this->assertStringContainsString('Private teacher observation', $html);
                $this->assertStringContainsString('id="profile-teacher-comment"', $html);
            }
        }
    }

    public function testPersonalShopListsAreScopedAndPaginatedIndependently(): void
    {
        $owner = $this->user();
        $other = $this->user();
        for ($i = 1; $i <= 27; $i++) {
            DB::table('market_goods')->insert(['id' => $i, 'name' => 'Награда '.$i]);
            DB::table('market_deals')->insert(['user_id' => $owner->id, 'good_id' => $i, 'price' => 10, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('coin_transactions')->insert(['user_id' => $owner->id, 'price' => $i, 'comment' => 'Награда '.$i]);
        }
        DB::table('market_goods')->insert(['id' => 99, 'name' => 'Чужая покупка']);
        DB::table('market_deals')->insert(['user_id' => $other->id, 'good_id' => 99]);
        DB::table('coin_transactions')->insert(['user_id' => $other->id, 'price' => 500, 'comment' => 'Чужая операция']);
        $response = $this->actingAs($owner)->get('/insider/market?purchases_page=2&history_page=2&user_id='.$other->id)
            ->assertOk()->assertDontSee('Чужая покупка')->assertDontSee('Чужая операция');
        foreach (['myPurchases', 'coinTransactions'] as $key) {
            $paginator = $response->viewData($key);
            $this->assertSame(27, $paginator->total());
            $this->assertSame(2, $paginator->count());
            $this->assertSame(2, $paginator->currentPage());
            $this->assertTrue($paginator->every(fn ($record) => $record->user_id === $owner->id));
        }
        $this->assertStringContainsString('#market-purchases', $response->viewData('myPurchases')->url(1));
        $this->assertStringContainsString('#market-history', $response->viewData('coinTransactions')->url(1));
    }

    private function addPersonalColumns(): void
    {
        Schema::table('users', function ($table) {
            foreach (['school', 'git', 'telegram', 'hobbies', 'interests', 'comments', 'gender'] as $name) $table->text($name)->nullable();
            $table->integer('grade_year')->nullable();
            $table->date('birthday')->nullable();
        });
    }

    private function user(string $role = 'student'): User
    {
        $user = User::create(['name' => 'Участник', 'email' => uniqid('person_').'@example.test', 'password' => bcrypt('old-secret'), 'role' => $role, 'email_verified_at' => now()]);
        foreach (['courses', 'managed_courses', 'completedCourses'] as $relation) $user->setRelation($relation, collect());
        return $user;
    }
}
