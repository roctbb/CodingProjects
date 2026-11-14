<?php
namespace Tests\Feature;

use App\Course;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class BusinessEnrollmentTest extends DeadlineFeatureCase
{
    private function registration(array $extra=[]): array
    {
        return $extra + ['invite'=>'AUDIT','name'=>'New student','email'=>'new-audit@example.test','password'=>'test-password',
            'password_confirmation'=>'test-password','gender'=>'boy','school'=>'School','grade'=>8,'birthday'=>'2011-02-03'];
    }

    protected function setUp(): void
    {
        parent::setUp();config(['app.enable_recaptcha'=>false,'app.enable_email_verification'=>false]);
        Event::fake([Registered::class]);
        DB::table('courses')->where('id',1)->update(['invite'=>'AUDIT','remote_invite'=>'AUDIT-R','mode'=>'open']);
    }

    public function test_remote_invitation_has_the_same_meaning_for_registration_and_joining(): void
    {
        $this->post('/register',$this->registration(['invite'=>'AUDIT-R']))->assertRedirect('/insider/courses');
        $new=User::where('email','new-audit@example.test')->firstOrFail();
        $this->assertDatabaseHas('course_students',['course_id'=>1,'user_id'=>$new->id,'is_remote'=>true]);
        DB::table('course_students')->where('course_id',1)->where('user_id',1)->delete();
        $this->actingAs(User::find(1))->get('/insider/invite?invite=AUDIT-R')->assertRedirect();
        $this->assertDatabaseHas('course_students',['course_id'=>1,'user_id'=>1,'is_remote'=>true]);
        $this->get('/insider/invite?invite=AUDIT-R')->assertRedirect();
        $this->assertEquals(1,DB::table('course_students')->where('course_id',1)->where('user_id',1)->count());
    }

    public function test_registration_course_link_does_not_duplicate_invitation_membership(): void
    {
        $this->post('/register',$this->registration(['course_id'=>1]))->assertRedirect('/insider/courses');
        $user=User::where('email','new-audit@example.test')->firstOrFail();
        $this->assertEquals(1,DB::table('course_students')->where('course_id',1)->where('user_id',$user->id)->count());
        Event::assertDispatchedTimes(Registered::class,1);
    }

    public function test_failed_membership_rolls_back_account_creation_before_registered_event(): void
    {
        DB::statement("CREATE TRIGGER fail_enrollment BEFORE INSERT ON course_students BEGIN SELECT RAISE(ABORT,'enrollment failed'); END");
        $this->post('/register',$this->registration())->assertStatus(500);
        $this->assertDatabaseMissing('users',['email'=>'new-audit@example.test']);Event::assertNotDispatched(Registered::class);
    }

    public function test_membership_migration_merges_duplicates_and_preserves_hidden_flag(): void
    {
        $migration=require database_path('migrations/2026_11_14_220000_harden_business_processes.php');$migration->down();
        DB::table('course_students')->where('course_id',1)->where('user_id',1)->delete();
        DB::table('course_students')->insert([['course_id'=>1,'user_id'=>1,'is_remote'=>false,'hidden_from_stats'=>false],
            ['course_id'=>1,'user_id'=>1,'is_remote'=>true,'hidden_from_stats'=>true]]);
        $migration->up();
        $this->assertEquals(1,DB::table('course_students')->where('course_id',1)->where('user_id',1)->count());
        $this->assertDatabaseHas('course_students',['course_id'=>1,'user_id'=>1,'hidden_from_stats'=>true,'is_remote'=>false]);
        try { DB::table('course_students')->insert(['course_id'=>1,'user_id'=>1]);$this->fail('Expected membership constraint'); }
        catch (\Illuminate\Database\UniqueConstraintViolationException $e) { $this->assertStringContainsString('UNIQUE',$e->getMessage()); }
    }

    public function test_migration_preserves_xp_but_recovers_unambiguous_incorrect_quiz_evaluation(): void
    {
        $migration=require database_path('migrations/2026_11_14_220000_harden_business_processes.php');$migration->down();
        DB::table('tasks')->where('id',11)->update(['is_quiz'=>true,'max_mark'=>1]);
        $id=DB::table('solutions')->insertGetId(['task_id'=>11,'course_id'=>1,'user_id'=>1,'text'=>'wrong',
            'submitted'=>now(),'mark'=>1,'raw_mark'=>1,'comment'=>'Неверный ответ.']);
        $balance=User::find(1)->balance();$migration->up();
        $answer=\App\Solution::find($id);
        $this->assertEquals(1,$answer->mark);$this->assertSame(0,$answer->evaluated_mark);
        $this->assertEquals($balance,User::find(1)->balance());
    }

    public function test_manual_date_cannot_bypass_existing_holidays_and_rejection_does_not_change_revision(): void
    {
        $planner=DB::table('planner_courses')->where('course_id',1)->first();
        DB::table('planner_calendar_exceptions')->insert(['planner_course_id'=>$planner->id,'starts_on'=>'2026-12-01','ends_on'=>'2026-12-01','reason'=>'Holiday']);
        $url='/insider/courses/1/planner/api/commands/add_slot';
        $body=['operation_id'=>(string)Str::uuid(),'revision'=>$planner->revision,'date'=>'2026-12-01','start_time'=>'14:00','duration_minutes'=>90];
        $this->actingAs(User::find(2))->postJson($url,$body)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseMissing('planner_calendar_slots',['planner_course_id'=>$planner->id,'local_date'=>'2026-12-01']);
        $this->assertEquals($planner->revision,DB::table('planner_courses')->where('id',$planner->id)->value('revision'));
        $this->postJson($url,array_replace($body,['date'=>'2026-12-02']))->assertOk();
    }
}
