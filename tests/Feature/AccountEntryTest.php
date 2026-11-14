<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AccountEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:', 'app.enable_recaptcha'=>false, 'app.enable_email_verify'=>false]);
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Event::fake([Registered::class]);
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password TEXT, gender TEXT,
                role TEXT DEFAULT 'student', birthday_hidden INTEGER DEFAULT 0, provider_id INTEGER, birthday TEXT, grade_year INTEGER, school TEXT, git TEXT, telegram TEXT,
                hobbies TEXT, interests TEXT, image TEXT, email_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE providers (id INTEGER PRIMARY KEY, invite TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, invite TEXT, mode TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER);
            INSERT INTO providers VALUES (1,'TEACHER'), (2,NULL);
            INSERT INTO courses VALUES (1,'Python','STUDENT','open');
            SQL);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function data(array $overrides=[]): array
    {
        return array_replace(['invite'=>'STUDENT','name'=>'Участник','email'=>'person@example.test',
            'password'=>'secret-pass','password_confirmation'=>'secret-pass','birthday'=>'2011-02-03',
            'gender'=>'boy','school'=>'School','grade'=>'8'], $overrides);
    }

    public function testInvalidInviteIsAFieldErrorAndDoesNotFlashPasswords(): void
    {
        $this->from('/register')->post('/register', $this->data(['invite'=>'WRONG']))
            ->assertRedirect('/register')->assertSessionHasErrors('invite')
            ->assertSessionHas('_old_input.name','Участник')->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users',0);
        $this->assertDatabaseCount('course_students',0);
    }

    public function testEmptyInviteCannotMatchAProviderWithNoCode(): void
    {
        $this->postJson('/register',$this->data(['invite'=>'']))->assertUnprocessable()->assertJsonValidationErrors('invite');
        $this->assertDatabaseCount('users',0);
    }

    public function testBothInviteTypesRetainProfileValidation(): void
    {
        foreach(['STUDENT','TEACHER'] as $invite){
            $this->postJson('/register',$this->data(['invite'=>$invite,'birthday'=>'not-a-date','grade'=>13]))
                ->assertUnprocessable()->assertJsonValidationErrors(['birthday','grade']);
        }
        $this->assertDatabaseCount('users',0);
    }

    public function testValidStudentRegistrationKeepsItsEnrollmentAndPasswordContract(): void
    {
        $this->post('/register',$this->data())->assertRedirect('/insider/courses')->assertSessionHasNoErrors();
        $user=User::firstOrFail();
        $this->assertSame('student',$user->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret-pass',$user->password));
        $this->assertDatabaseHas('course_students',['course_id'=>1,'user_id'=>$user->id]);
        Event::assertDispatched(Registered::class);
    }

    public function testRegistrationAlwaysOffersItsRequiredInviteIncludingCourseLinks(): void
    {
        $this->get('/register?course_id=1')->assertOk()->assertSee('name="invite"',false)->assertSee('data-registration-progress',false);
    }

    public function testLoginAndResetDoNotLoadRegistrationCaptcha(): void
    {
        config(['app.enable_recaptcha'=>true]);
        foreach(['/login','/password/reset','/password/reset/token'] as $path){
            $this->get($path)->assertOk()->assertDontSee('recaptcha/api.js',false)->assertSee('auth-shell-background',false);
        }
    }

    public function testEnabledRegistrationCaptchaUsesCompactWidget(): void
    {
        config(['app.enable_recaptcha'=>true]);
        $this->get('/register')->assertOk()->assertSee('data-size="compact"',false)->assertSee('recaptcha/api.js',false);
    }

    public function testResetFormMatchesItsExistingEightCharacterMinimum(): void
    {
        $this->get('/password/reset/token')->assertOk()->assertSee('minlength="8"',false);
        $this->postJson('/password/reset',['token'=>'token','email'=>'person@example.test','password'=>'123456','password_confirmation'=>'123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
