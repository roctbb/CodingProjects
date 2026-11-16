<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class PlannerSessionBonusTest extends DeadlineFeatureCase
{
    private function command(string $operation, array $input, ?string $key = null)
    {
        return $this->postJson('/insider/courses/1/planner/api/commands/'.$operation, $input + [
            'operation_id' => $key ?? (string) Str::uuid(),
            'revision' => (int) DB::table('planner_courses')->where('course_id', 1)->value('revision'),
        ]);
    }

    public function test_one_command_updates_all_native_plan_materials_and_can_be_undone(): void
    {
        $this->actingAs(User::find(2));
        $session = (int) DB::table('planner_session_resources')->where('id', 1)->value('session_id');
        $entry = DB::table('planner_session_resources')->where('id', 1)->first();
        DB::table('planner_session_resources')->insert(['session_id' => $session, 'resource_ref_id' => $entry->resource_ref_id,
            'layer' => 'fact', 'resource_snapshot' => $entry->resource_snapshot, 'class_bonus_gc' => 19]);
        $before = DB::table('planner_session_resources')->pluck('class_bonus_gc', 'id')->all();
        $key = (string) Str::uuid();
        $this->command('session_bonus', ['session_id' => $session, 'class_bonus_gc' => 7], $key)->assertOk()->assertJsonPath('result.updated_entries', 3);
        $this->assertSame([7, 7, 7], DB::table('planner_session_resources')->where('session_id', $session)->where('layer', 'plan')->pluck('class_bonus_gc')->all());
        $this->assertSame(19, DB::table('planner_session_resources')->where('layer', 'fact')->value('class_bonus_gc'));
        $change = DB::table('planner_changes')->orderByDesc('id')->first();
        $this->command('session_bonus', ['session_id' => $session, 'class_bonus_gc' => 7], $key)->assertOk()->assertJsonPath('replayed', true);
        $this->command('undo', ['change_id' => $change->id])->assertOk();
        $this->assertSame($before, DB::table('planner_session_resources')->pluck('class_bonus_gc', 'id')->all());
        $this->assertDatabaseCount('classroom_bonus_awards', 0);
    }

    public function test_zero_removes_bonus_and_invalid_amounts_do_not_change_the_plan(): void
    {
        $this->actingAs(User::find(2));
        $session = (int) DB::table('planner_session_resources')->where('id', 1)->value('session_id');
        $this->command('session_bonus', ['session_id' => $session, 'class_bonus_gc' => 7])->assertOk();
        $this->command('session_bonus', ['session_id' => $session, 'class_bonus_gc' => 0])->assertOk();
        $this->assertSame(0, DB::table('planner_session_resources')->where('id', 1)->value('class_bonus_gc'));
        $revision = DB::table('planner_courses')->where('course_id', 1)->value('revision');
        foreach ([-1, 10001, 1.5, 'bad', [7]] as $amount) $this->command('session_bonus', ['session_id' => $session, 'class_bonus_gc' => $amount])->assertUnprocessable();
        $this->assertSame($revision, DB::table('planner_courses')->where('course_id', 1)->value('revision'));
    }

    public function test_foreign_sessions_empty_sessions_and_non_managers_are_rejected(): void
    {
        $this->actingAs(User::find(2));
        $planner = DB::table('planner_courses')->insertGetId(['course_id' => 2, 'timezone' => 'Europe/Moscow']);
        $foreign = DB::table('planner_sessions')->insertGetId(['planner_course_id' => $planner, 'title' => 'Other course']);
        $this->command('session_bonus', ['session_id' => $foreign, 'class_bonus_gc' => 7])->assertNotFound();
        $empty = DB::table('planner_sessions')->insertGetId(['planner_course_id' => 1, 'title' => 'Empty']);
        $this->command('session_bonus', ['session_id' => $empty, 'class_bonus_gc' => 7])->assertUnprocessable();
        $this->actingAs(User::find(1));
        $this->command('session_bonus', ['session_id' => 1, 'class_bonus_gc' => 7])->assertForbidden();
    }
}
