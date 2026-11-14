<?php

namespace Tests\Feature;

use App\Course;
use App\CoursePlanner\Services\PlannerService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoursePlannerTest extends TestCase
{
    private string $api = '/insider/courses/1/planner/api';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'course_planner.enabled' => true]);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            PRAGMA foreign_keys = ON;
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, email_verified_at TEXT, image TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, state TEXT, start_date TEXT, end_date TEXT);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, sort_index INTEGER);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, chapter_id INTEGER, sort_index INTEGER, updated_at TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, name TEXT, lesson_id INTEGER, program_id INTEGER, sort_index INTEGER, updated_at TEXT);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, name TEXT, step_id INTEGER, max_mark INTEGER, is_star INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0, updated_at TEXT);
            CREATE TABLE task_deadlines (id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER, task_id INTEGER, expiration TEXT, penalty NUMERIC, created_at TEXT, updated_at TEXT);
            INSERT INTO users VALUES (1,'Teacher','teacher@example.test','teacher','2026-01-01',null),(2,'Other teacher','other@example.test','teacher','2026-01-01',null),(3,'Student','student@example.test','student','2026-01-01',null);
            INSERT INTO programs VALUES (1,'Program'),(2,'Other program');
            INSERT INTO courses VALUES (1,'Test course',1,'started','2026-09-01',null),(2,'Other course',2,'started',null,null);
            INSERT INTO course_teachers VALUES (1,1,0),(2,2,0);
            INSERT INTO course_students VALUES (1,3,0,0);
            INSERT INTO program_chapters VALUES (1,'Chapter',1,100),(2,'Other chapter',2,100);
            INSERT INTO lessons VALUES (1,'Recursion',1,1,100,'2026-11-01'),(2,'Files',1,1,200,'2026-11-01'),(3,'Other lesson',2,2,100,'2026-11-01');
            INSERT INTO program_steps VALUES (1,'Base case',1,1,100,'2026-11-01'),(2,'Call stack',1,1,200,'2026-11-01'),(3,'Open file',2,1,100,'2026-11-01'),(4,'Other step',3,2,100,'2026-11-01');
            INSERT INTO tasks VALUES (1,'Stairs',1,10,0,0,'2026-11-01'),(2,'Bonus',2,20,1,0,'2026-11-01'),(3,'Hidden',2,5,0,1,'2026-11-01'),(4,'Other task',4,10,0,0,'2026-11-01');
            SQL);
        (require base_path('database/migrations/2026_11_10_120000_create_course_planner_tables.php'))->up();
        (require base_path('database/migrations/2026_11_11_180000_add_comment_to_planner_sessions.php'))->up();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Moscow'));
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-11-10 12:00:00', 'Europe/Moscow'));
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        \Carbon\CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function command(string $operation, array $data = [], ?string $id = null)
    {
        $revision = (int) DB::table('planner_courses')->where('course_id', 1)->value('revision');
        return $this->postJson($this->api.'/commands/'.$operation, $data + ['revision' => $revision, 'operation_id' => $id ?? (string) Str::uuid()]);
    }

    private function configure(): array
    {
        $this->command('configure', ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-27', 'timezone' => 'Europe/Moscow', 'default_duration_minutes' => 90,
            'rules' => [['rule_key' => 'monday', 'weekday' => 1, 'start_time' => '14:00', 'duration_minutes' => 90],
                ['rule_key' => 'thursday', 'weekday' => 4, 'start_time' => '14:00', 'duration_minutes' => 90]]])->assertOk();
        return DB::table('planner_sessions as s')->join('planner_calendar_slots as c', 'c.id', '=', 's.calendar_slot_id')->orderBy('c.local_date')->pluck('s.id')->all();
    }

    private function attach(int $session, string $type = 'step', string $id = '1'): int
    {
        return $this->command('attach', ['session_id' => $session, 'layer' => 'plan', 'provider_key' => 'codingprojects', 'resource_type' => $type, 'external_id' => $id])
            ->assertOk()->json('result.link_id');
    }

    public function test_only_teacher_of_this_course_or_admin_has_access(): void
    {
        $this->getJson($this->api.'/state')->assertOk();
        $this->actingAs(User::find(2))->getJson($this->api.'/state')->assertForbidden();
        $this->actingAs(User::find(3))->getJson($this->api.'/state')->assertForbidden();
        $this->getJson('/insider/courses/2/planner/api/state')->assertForbidden();
    }

    public function test_planner_page_loads_its_assets_and_never_exposes_integration_secrets(): void
    {
        $this->get('/insider/courses/1/planner')->assertOk()->assertSee('План занятий')->assertSee('coursePlanner.js')->assertSee('planner-search');
    }

    public function test_calendar_is_idempotent_and_does_not_change_course_lifecycle(): void
    {
        $sessions = $this->configure();
        $this->assertCount(8, $sessions);
        $this->assertSame($sessions, $this->configure());
        $this->assertSame('2026-09-01', DB::table('courses')->where('id', 1)->value('start_date'));
        $this->assertSame(8, DB::table('planner_calendar_slots')->count());
    }

    public function test_excluded_date_preserves_content_in_backlog_and_can_be_undone(): void
    {
        $sessions = $this->configure();
        $this->attach($sessions[2]);
        $this->command('add_exception', ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'reason' => 'Holiday'])->assertOk();
        $this->assertNull(DB::table('planner_sessions')->where('id', $sessions[2])->value('calendar_slot_id'));
        $this->assertSame(1, DB::table('planner_session_resources')->count());
        $change = DB::table('planner_changes')->latest('id')->first();
        $this->command('undo', ['change_id' => $change->id])->assertOk();
        $this->assertNotNull(DB::table('planner_sessions')->where('id', $sessions[2])->value('calendar_slot_id'));
        $this->assertSame(0, DB::table('planner_calendar_exceptions')->count());
    }

    public function test_catalog_search_and_pagination_are_bounded_and_course_scoped(): void
    {
        $page = $this->getJson($this->api.'/providers/codingprojects/resources?limit=1&type=step')->assertOk();
        $this->assertCount(1, $page->json('catalog.items'));
        $this->assertTrue($page->json('catalog.page.has_more'));
        $cursor = $page->json('catalog.page.next_cursor');
        $next = $this->getJson($this->api.'/providers/codingprojects/resources?limit=1&type=step&cursor='.urlencode($cursor))->assertOk();
        $this->assertNotSame($page->json('catalog.items.0.external_id'), $next->json('catalog.items.0.external_id'));
        $search = $this->getJson($this->api.'/providers/codingprojects/resources?query=stack')->assertOk();
        $this->assertCount(1, $search->json('catalog.items'));
        $this->assertSame('Call stack', $search->json('catalog.items.0.title'));
        $this->getJson($this->api.'/providers/codingprojects/resources?limit=1&type=task&cursor='.urlencode($cursor))->assertStatus(409);
    }

    public function test_whole_lesson_can_be_split_without_duplicate_steps(): void
    {
        $sessions = $this->configure();
        $whole = $this->attach($sessions[0], 'lesson');
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'plan', 'provider_key' => 'codingprojects', 'resource_type' => 'step', 'external_id' => '1'])->assertStatus(422);
        $this->command('split_lesson', ['link_id' => $whole, 'selected_ids' => ['2'], 'target_session_id' => $sessions[1]])->assertOk();
        $this->assertSame(2, DB::table('planner_session_resources')->count());
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27')->assertOk();
        $this->assertSame(2, $state->json('summary.rows.0.session_count'));
        $change = DB::table('planner_changes')->latest('id')->first();
        $this->command('undo', ['change_id' => $change->id])->assertOk();
        $this->assertSame($whole, DB::table('planner_session_resources')->value('id'));
    }

    public function test_resource_used_twice_counts_once_and_deadline_is_shared(): void
    {
        $sessions = $this->configure();
        $first = $this->attach($sessions[0], 'task');
        $this->attach($sessions[1], 'task');
        $ref = DB::table('planner_session_resources')->where('id', $first)->value('resource_ref_id');
        $this->command('set_deadline', ['resource_ref_id' => $ref, 'due_on' => '2026-11-12', 'penalty' => 0.1])->assertOk();
        $this->assertSame(1, DB::table('task_deadlines')->count());
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27')->assertOk();
        $this->assertSame(1, $state->json('summary.rows.0.providers.codingprojects.materials'));
        $this->assertSame(2, $state->json('summary.rows.0.providers.codingprojects.uses'));
        $this->assertSame(1, $state->json('summary.rows.0.deadline_count'));
        $this->assertSame(1, $state->json('summary.rows.0.task_count'));
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $first, 'target_session_id' => $sessions[3]])->assertOk();
        $this->assertSame('2026-11-12', substr(DB::table('task_deadlines')->value('expiration'), 0, 10));
        $this->command('delete_entry', ['kind' => 'resource', 'entry_id' => $first])->assertOk();
        $this->assertSame(1, DB::table('task_deadlines')->count());
    }

    public function test_workspace_preserves_legacy_materials_without_displaying_duplicates(): void
    {
        $sessions = $this->configure();
        $plan = $this->attach($sessions[0], 'step', '1');
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'fact', 'provider_key' => 'codingprojects', 'resource_type' => 'step', 'external_id' => '1'])->assertOk();
        $factOnly = $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'fact', 'provider_key' => 'codingprojects', 'resource_type' => 'task', 'external_id' => '2'])->assertOk()->json('result.link_id');
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'plan', 'unified' => true, 'provider_key' => 'codingprojects', 'resource_type' => 'task', 'external_id' => '2'])
            ->assertOk()->assertJsonPath('result.link_id', $factOnly);
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27&layer=workspace')->assertOk();
        $session = collect($state->json('sessions'))->firstWhere('id', $sessions[0]);
        $this->assertSame([$plan, $factOnly], array_column($session['entries'], 'id'));
        $this->assertSame(2, $state->json('summary.rows.0.providers.codingprojects.uses'));
        $this->assertSame(3, DB::table('planner_session_resources')->count());
        $this->command('delete_entry', ['kind' => 'resource', 'entry_id' => $plan, 'unified' => true])->assertOk();
        $this->assertSame(1, DB::table('planner_session_resources')->count());
    }

    public function test_batch_attachment_is_atomic_and_deduplicated(): void
    {
        $sessions = $this->configure();
        $resource = ['provider_key' => 'codingprojects', 'resource_type' => 'step', 'external_id' => '1'];
        $result = $this->command('attach_many', ['session_id' => $sessions[0], 'unified' => true, 'items' => [$resource, $resource]])->assertOk();
        $this->assertCount(1, $result->json('result.link_ids'));
        $this->assertSame(1, DB::table('planner_session_resources')->count());
        $this->command('attach_many', ['session_id' => $sessions[1], 'unified' => true, 'items' => [$resource, array_replace($resource, ['external_id' => '4'])]])->assertNotFound();
        $this->assertSame(0, DB::table('planner_session_resources')->where('session_id', $sessions[1])->count());
        $this->command('attach_many', ['session_id' => $sessions[1], 'unified' => true, 'items' => [$resource]])->assertOk();
        $this->assertSame(2, DB::table('planner_session_resources')->count());
    }

    public function test_comment_is_only_for_ended_sessions_and_does_not_confirm_attendance(): void
    {
        $sessions = $this->configure();
        $this->command('comment', ['session_id' => $sessions[3], 'comment' => 'Future'])->assertStatus(422);
        Carbon::setTestNow('2026-11-09 14:30:00 Europe/Moscow');
        \Carbon\CarbonImmutable::setTestNow('2026-11-09 14:30:00 Europe/Moscow');
        $this->command('comment', ['session_id' => $sessions[2], 'comment' => 'Still in progress'])->assertStatus(422);
        Carbon::setTestNow('2026-11-09 16:00:00 Europe/Moscow');
        \Carbon\CarbonImmutable::setTestNow('2026-11-09 16:00:00 Europe/Moscow');
        $this->command('comment', ['session_id' => $sessions[2], 'comment' => "Разобрали базовый случай.\nПрактику продолжим в четверг."])->assertOk();
        $this->assertSame(0, DB::table('planner_session_records')->count());
        $this->assertStringContainsString('Практику продолжим', DB::table('planner_sessions')->where('id', $sessions[2])->value('comment'));
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27&layer=workspace')->assertOk();
        $this->assertTrue(collect($state->json('sessions'))->firstWhere('id', $sessions[2])['can_comment']);
        $this->assertFalse(collect($state->json('sessions'))->firstWhere('id', $sessions[3])['can_comment']);
        $change = DB::table('planner_changes')->latest('id')->first();
        $this->command('undo', ['change_id' => $change->id])->assertOk();
        $this->assertNull(DB::table('planner_sessions')->where('id', $sessions[2])->value('comment'));
    }

    public function test_comment_keeps_its_date_when_schedule_is_rebuilt(): void
    {
        $sessions = $this->configure();
        $slot = DB::table('planner_sessions')->where('id', $sessions[2])->value('calendar_slot_id');
        $this->command('comment', ['session_id' => $sessions[2], 'comment' => 'Разобрали рекурсию'])->assertOk();
        $this->command('add_exception', ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'reason' => 'Изменение календаря'])->assertOk();
        $session = DB::table('planner_sessions')->where('id', $sessions[2])->first();
        $this->assertSame($slot, $session->calendar_slot_id);
        $this->assertSame('Разобрали рекурсию', $session->comment);
        $this->assertSame(0, DB::table('planner_session_records')->count());
    }

    public function test_unified_move_keeps_legacy_copies_together_and_rejects_target_duplicates(): void
    {
        $sessions = $this->configure();
        $plan = $this->attach($sessions[0]);
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'fact', 'provider_key' => 'codingprojects', 'resource_type' => 'step', 'external_id' => '1'])->assertOk();
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $plan, 'target_session_id' => $sessions[1], 'unified' => true])->assertOk();
        $this->assertSame(2, DB::table('planner_session_resources')->where('session_id', $sessions[1])->count());
        $other = $this->attach($sessions[2]);
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $other, 'target_session_id' => $sessions[1], 'unified' => true])->assertStatus(422);
        $this->assertSame($sessions[2], DB::table('planner_session_resources')->where('id', $other)->value('session_id'));
    }

    public function test_unified_ordering_can_place_a_legacy_fact_material_before_a_plan_material(): void
    {
        $sessions = $this->configure();
        $plan = $this->attach($sessions[0]);
        $fact = $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'fact', 'provider_key' => 'codingprojects', 'resource_type' => 'task', 'external_id' => '2'])->assertOk()->json('result.link_id');
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $fact, 'target_session_id' => $sessions[0], 'before' => ['kind' => 'resource', 'id' => $plan], 'unified' => true])->assertOk();
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27&layer=workspace')->assertOk();
        $entries = collect($state->json('sessions'))->firstWhere('id', $sessions[0])['entries'];
        $this->assertSame([$fact, $plan], array_column($entries, 'id'));
    }

    public function test_future_date_is_not_fact_and_confirmed_fact_keeps_snapshot(): void
    {
        $sessions = $this->configure();
        $plan = $this->attach($sessions[0]);
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-11-20', 'status' => 'confirmed', 'from_plan' => true])->assertStatus(422);
        $this->assertSame(0, DB::table('planner_session_records')->count());
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-11-02', 'status' => 'confirmed', 'from_plan' => true, 'note' => 'Covered base case'])->assertOk();
        $this->command('delete_entry', ['kind' => 'resource', 'entry_id' => $plan])->assertOk();
        DB::table('program_steps')->where('id', 1)->update(['name' => 'Changed source']);
        $state = $this->getJson($this->api.'/state?from=2026-11-02&to=2026-11-27&layer=fact')->assertOk();
        $fact = collect($state->json('sessions'))->firstWhere('id', $sessions[0]);
        $this->assertSame('Base case', $fact['entries'][0]['resource']['title']);
        $this->assertSame(1, $state->json('summary.rows.0.providers.codingprojects.materials'));
    }

    public function test_conflict_and_replayed_operation_do_not_duplicate_data(): void
    {
        $sessions = $this->configure();
        $id = (string) Str::uuid();
        $data = ['session_id' => $sessions[0], 'layer' => 'plan', 'text' => 'Project defense'];
        $first = $this->command('add_text', $data, $id)->assertOk();
        $this->command('add_text', $data, $id)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, DB::table('planner_session_texts')->count());
        $this->postJson($this->api.'/commands/add_text', $data + ['revision' => 0, 'operation_id' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame($first->json('revision'), DB::table('planner_courses')->value('revision'));
    }

    public function test_foreign_program_resource_and_cross_course_session_are_rejected(): void
    {
        $sessions = $this->configure();
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'plan', 'provider_key' => 'codingprojects', 'resource_type' => 'step', 'external_id' => '4'])->assertNotFound();
        $service = app(PlannerService::class);
        $other = $service->planner(Course::findOrFail(2));
        $foreign = DB::table('planner_sessions')->insertGetId(['planner_course_id' => $other->id, 'title' => 'Other']);
        $link = $this->attach($sessions[0]);
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $link, 'target_session_id' => $foreign])->assertNotFound();
        $this->assertSame($sessions[0], DB::table('planner_session_resources')->value('session_id'));
    }

    public function test_remote_provider_preserves_string_ids_and_checks_access_on_attach(): void
    {
        config(['course_planner.providers.example' => ['name' => 'Example', 'driver' => 'http', 'url' => 'https://example.test', 'secret' => str_repeat('x', 64)]]);
        $card = ['external_id' => '0001', 'type' => 'game', 'type_label' => 'Game', 'title' => 'Team game', 'open_url' => 'https://example.test/game/0001', 'public_metadata' => []];
        Http::fake([
            '*manifest*' => Http::response(['protocol_version' => '1', 'provider_key' => 'example', 'resource_types' => [['key' => 'game', 'label' => 'Game']], 'pagination' => ['mode' => 'cursor'], 'search' => ['server_side' => true]]),
            '*resources/detail*' => Http::response(['error' => 'denied'], 403),
            '*resources*' => Http::response(['items' => [$card], 'page' => ['has_more' => false, 'next_cursor' => null]]),
        ]);
        $sessions = $this->configure();
        $this->getJson($this->api.'/providers/example/resources')->assertOk()->assertJsonPath('catalog.items.0.external_id', '0001');
        $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'plan', 'provider_key' => 'example', 'resource_type' => 'game', 'external_id' => '0001'])->assertForbidden();
        $this->assertSame(0, DB::table('planner_session_resources')->count());
    }

    public function test_move_and_split_reject_overlap_with_a_whole_lesson_atomically(): void
    {
        $sessions = $this->configure();
        $step = $this->attach($sessions[0]);
        $whole = $this->attach($sessions[1], 'lesson');
        $this->command('move_entry', ['kind' => 'resource', 'entry_id' => $step, 'target_session_id' => $sessions[1]])->assertStatus(422);
        $this->assertSame($sessions[0], DB::table('planner_session_resources')->where('id', $step)->value('session_id'));
        $secondWhole = $this->attach($sessions[2], 'lesson');
        $this->command('split_lesson', ['link_id' => $whole, 'selected_ids' => ['2'], 'target_session_id' => $sessions[2]])->assertStatus(422);
        $this->assertSame(3, DB::table('planner_session_resources')->count());
        $this->assertTrue(DB::table('planner_session_resources')->where('id', $whole)->exists());
        $this->assertTrue(DB::table('planner_session_resources')->where('id', $secondWhole)->exists());
    }

    public function test_direct_fact_has_its_own_deadline_snapshot_and_date(): void
    {
        $sessions = $this->configure();
        $this->command('set_deadline', ['task_id' => 1, 'due_on' => '2026-11-12'])->assertOk();
        $fact = $this->command('attach', ['session_id' => $sessions[0], 'layer' => 'fact', 'provider_key' => 'codingprojects', 'resource_type' => 'task', 'external_id' => '1'])->assertOk()->json('result.link_id');
        $this->command('update_entry', ['kind' => 'resource', 'entry_id' => $fact, 'fact_state' => 'done'])->assertOk();
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-10-30', 'status' => 'confirmed'])->assertOk();
        $this->command('set_deadline', ['task_id' => 1, 'due_on' => '2026-11-25'])->assertOk();
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-10-30', 'status' => 'confirmed', 'note' => 'Correction'])->assertOk();
        $state = $this->getJson($this->api.'/state?from=2026-10-29&to=2026-10-31&layer=fact')->assertOk();
        $this->assertCount(0, $state->json('slots'));
        $this->assertCount(1, $state->json('sessions'));
        $this->assertSame('2026-10-30', $state->json('sessions.0.record.held_on'));
        $this->assertSame('2026-11-12', $state->json('sessions.0.entries.0.deadline.due_on'));
        $this->assertSame('2026-11-25', $state->json('deadlines.0.due_on'));
    }

    public function test_undo_never_overwrites_a_deadline_changed_by_legacy_screens(): void
    {
        $this->configure();
        $this->command('set_deadline', ['task_id' => 1, 'due_on' => '2026-11-12'])->assertOk();
        $change = DB::table('planner_changes')->latest('id')->first();
        DB::table('task_deadlines')->update(['expiration' => '2026-11-20']);
        $this->command('undo', ['change_id' => $change->id])->assertStatus(409);
        $this->assertSame('2026-11-20', DB::table('task_deadlines')->value('expiration'));
        $this->command('set_deadline', ['task_id' => 4, 'due_on' => '2026-11-12'])->assertNotFound();
    }

    public function test_refresh_is_explicit_and_never_rewrites_fact(): void
    {
        $sessions = $this->configure();
        $plan = $this->attach($sessions[0]);
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-11-02', 'status' => 'confirmed', 'from_plan' => true])->assertOk();
        DB::table('program_steps')->where('id', 1)->update(['name' => 'Updated base case']);
        $this->command('refresh_entry', ['entry_id' => $plan])->assertOk();
        $fact = DB::table('planner_session_resources')->where('layer', 'fact')->first();
        $this->assertSame('Base case', json_decode($fact->resource_snapshot, true)['title']);
        $this->assertSame('Updated base case', json_decode(DB::table('planner_session_resources')->where('id', $plan)->value('resource_snapshot'), true)['title']);
        $this->command('refresh_entry', ['entry_id' => $fact->id])->assertStatus(422);
        $details = $this->getJson($this->api.'/summary/details?layer=fact&lesson_id=1&provider=codingprojects')->assertOk();
        $this->assertSame('Base case', $details->json('items.0.title'));
        $this->getJson($this->api.'/summary/details?layer=fact&lesson_id=3&provider=codingprojects')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_large_catalog_is_paged_in_sql_and_search_treats_wildcards_literally(): void
    {
        for ($chunk = 0; $chunk < 10; $chunk++) {
            $rows = [];
            for ($i = 0; $i < 1000; $i++) {
                $id = 10 + $chunk * 1000 + $i;
                $rows[] = ['id' => $id, 'name' => 'Practice '.$id, 'step_id' => 3, 'max_mark' => 10, 'is_star' => 0, 'is_hidden' => 0];
            }
            DB::table('tasks')->insert($rows);
        }
        DB::enableQueryLog();
        $page = $this->getJson($this->api.'/providers/codingprojects/resources?type=task&limit=50')->assertOk()->assertJsonCount(50, 'catalog.items');
        $this->assertTrue($page->json('catalog.page.has_more'));
        $catalogQuery = collect(DB::getQueryLog())->first(fn ($q) => str_contains($q['query'], 'as "catalog"'));
        $this->assertStringContainsString('limit 51', $catalogQuery['query']);
        DB::disableQueryLog();
        $this->getJson($this->api.'/providers/codingprojects/resources?query=%25')->assertOk()->assertJsonCount(0, 'catalog.items');
        $this->getJson($this->api.'/providers/codingprojects/resources?limit=51')->assertStatus(422);
    }

    public function test_first_confirmation_freezes_the_deadline_after_a_draft(): void
    {
        $sessions = $this->configure();
        $this->attach($sessions[0], 'task');
        $this->command('set_deadline', ['task_id' => 1, 'due_on' => '2026-11-12'])->assertOk();
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-11-02', 'status' => 'draft', 'from_plan' => true])->assertOk();
        $this->command('set_deadline', ['task_id' => 1, 'due_on' => '2026-11-20'])->assertOk();
        $this->command('record', ['session_id' => $sessions[0], 'held_on' => '2026-11-02', 'status' => 'confirmed'])->assertOk();
        $snapshot = json_decode(DB::table('planner_session_resources')->where('layer', 'fact')->value('resource_snapshot'), true);
        $this->assertSame('2026-11-20', $snapshot['deadline_snapshot']);
        $this->assertNotEmpty($snapshot['deadline_frozen_at']);
    }

    public function test_disabling_provider_preserves_materials_and_options_can_be_undone(): void
    {
        $sessions = $this->configure();
        $this->attach($sessions[0]);
        $this->getJson($this->api.'/state')->assertOk();
        $this->command('providers', ['providers' => [['key' => 'codingprojects', 'enabled' => false, 'show_in_summary' => false]]])->assertOk();
        $this->getJson($this->api.'/providers/codingprojects/resources')->assertNotFound();
        $this->getJson($this->api.'/state')->assertOk()->assertJsonPath('summary.rows.0.providers.codingprojects.materials', 1);
        $this->assertSame(1, DB::table('planner_session_resources')->count());
        $this->command('undo', ['change_id' => DB::table('planner_changes')->latest('id')->value('id')])->assertOk();
        $this->getJson($this->api.'/providers/codingprojects/resources')->assertOk();
    }
}
