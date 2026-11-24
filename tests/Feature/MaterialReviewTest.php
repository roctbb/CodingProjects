<?php
namespace Tests\Feature;

use App\MaterialReview\{Documents,Plan,Review,Publishing,Resources,Problem};
use App\User;
use Illuminate\Support\Facades\{DB,Queue,Notification,Http,Schema};
use Tests\TestCase;

class MaterialReviewTest extends TestCase
{
    private User $admin;
    private Review $review;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','queue.default'=>'sync']);
        DB::purge('sqlite'); Queue::fake(); Notification::fake(); Http::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        DB::unprepared(file_get_contents(base_path('scripts/ui-checks/fixture.sql')));
        (require database_path('migrations/2026_11_16_220000_create_material_review.php'))->up();
        DB::statement('CREATE UNIQUE INDEX mr_test_course_points ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX mr_test_lesson_points ON lesson_student_stats(course_id,lesson_id,student_id)');
        $task=(array)DB::table('tasks')->where('id',11)->first(); $task['id']=1; $task['sort_index']=0; DB::table('tasks')->insert($task);
        $this->admin=User::findOrFail(5); $this->actingAs($this->admin); $this->review=app(Review::class);
        DB::table('program_steps')->where('id',1)->update(['theory'=>"Первый абзац.\n\nСтарое объяснение.\n\nПоследний абзац.",'notes'=>'Приватные заметки.','is_notebook'=>false]);
        DB::table('tasks')->where('id',1)->update(['text'=>'Старое условие.','solution'=>'Авторское решение.','max_mark'=>10]);
    }
    protected function tearDown(): void { DB::disconnect('sqlite'); parent::tearDown(); }
    private function draft(array $lessons=[1],array $extra=[]): array
    {
        $bases=array_map(fn ($id)=>$this->review->documents->revision($id,'test',$this->admin->id)['id'],$lessons);
        return $this->review->create($this->admin,array_merge(['format_version'=>1,'installation_id'=>$this->review->documents->installation(),'program_id'=>1,'base_revision_ids'=>$bases,'title'=>'Тестовая редакция','reason'=>'Проверка ясности объяснения.'],$extra));
    }
    private function proposal(array $d,array $ops,array $extra=[]): array
    {
        $p=$this->review->propose($this->admin,$d['id'],array_merge(['external_id'=>'p-'.uniqid(),'title'=>'Исправление','reason'=>'Убираем неоднозначность для ученика.','operations'=>$ops],$extra),$d['version']);
        return [$this->review->get($this->admin,$d['id']),$p['proposal_id']];
    }
    private function accepted(array $d,array $ops,array $extra=[]): array
    { [$d,$p]=$this->proposal($d,$ops,$extra); return $this->review->decide($this->admin,$d['id'],$p,'accepted',$d['version']); }
    private function publish(array $d): array
    { $d=$this->review->select($this->admin,$d['id'],$d['version']); return app(Publishing::class)->publish($this->admin,$d['id'],$d['version']); }
    private function replacement(string $field='text',mixed $value='Новое условие.'): array
    { return ['op'=>'replace','entity'=>'task','id'=>1,'field'=>$field,'value'=>$value]; }

    public function test_snapshot_includes_notebook_tasks_ids_order_and_checking_settings(): void
    {
        $book=['nbformat'=>4,'metadata'=>['keep'=>'yes'],'cells'=>[['id'=>'intro','cell_type'=>'markdown','metadata'=>[],'source'=>'Текст']]];
        DB::table('program_steps')->where('id',1)->update(['is_notebook'=>1,'theory'=>Documents::encode($book)]);
        $doc=$this->review->documents->lesson(1);
        $this->assertSame(1,$doc['steps'][0]['id']); $this->assertNotEmpty($doc['steps'][0]['tasks']);
        $this->assertSame(1,$doc['steps'][0]['tasks'][0]['id']);
        $this->assertSame('Авторское решение.',$doc['steps'][0]['tasks'][0]['fields']['solution']);
        $this->assertSame($book,Documents::decode($doc['steps'][0]['fields']['theory']));
    }
    public function test_decisions_and_manual_undo_are_durable_without_publication(): void
    {
        $d=$this->draft(); [$d,$pid]=$this->proposal($d,[$this->replacement()]);
        foreach (['accepted','rejected','deferred','pending','accepted'] as $decision) {
            $d=$this->review->decide($this->admin,$d['id'],$pid,$decision,$d['version'],'Комментарий автора');
            $this->assertSame($decision,$d['proposals'][0]['decision']);
            $this->assertSame('Старое условие.',DB::table('tasks')->where('id',1)->value('text'));
        }
        $manual=$this->review->update($this->admin,$d['id'],['reason'=>'Уточнение автора.','operations'=>[$this->replacement('text','Авторский вариант.')]],$d['version']);
        $manualId=end($manual['proposals'])['id'];
        $undone=$this->review->decide($this->admin,$d['id'],$manualId,'pending',$manual['version']);
        $this->assertSame('Новое условие.',$undone['targets'][0]['steps'][0]['tasks'][0]['fields']['text']);
        $fresh=new Review(new Documents(),new Plan()); $this->assertSame($undone['targets'],$fresh->get($this->admin,$d['id'])['targets']);
    }
    public function test_publication_preserves_identity_settings_attempts_and_is_idempotent(): void
    {
        $before=(array)DB::table('tasks')->find(1); $attempts=DB::table('solutions')->where('task_id',1)->count();
        $d=$this->accepted($this->draft(),[$this->replacement()]); $pub=$this->publish($d);
        $after=(array)DB::table('tasks')->find(1); $this->assertSame('Новое условие.',$after['text']);
        unset($before['text'],$after['text'],$before['updated_at'],$after['updated_at'],$before['sort_index'],$after['sort_index']);
        $this->assertSame($before,$after); $this->assertSame($attempts,DB::table('solutions')->where('task_id',1)->count());
        $again=app(Publishing::class)->publish($this->admin,$d['id'],1); $this->assertTrue($again['duplicate']); $this->assertSame($pub['id'],$again['id']);
    }
    public function test_external_editor_conflicts_but_independent_fields_merge(): void
    {
        $d=$this->accepted($this->draft(),[$this->replacement()]);
        $task=\App\Task::find(1); $task->solution='Независимое авторское решение.'; $task->save();
        $this->assertTrue($this->review->check($this->admin,$d['id'])['valid']);
        $this->publish($d); $this->assertSame('Независимое авторское решение.',DB::table('tasks')->where('id',1)->value('solution'));
        $this->assertGreaterThanOrEqual(3,DB::table('material_review_revisions')->count());
        $e=$this->accepted($this->draft(),[$this->replacement('text','Ещё одно условие.')]);
        \App\Task::find(1)->update(['text'=>'Правка обычного редактора.']);
        $check=$this->review->check($this->admin,$e['id']); $this->assertFalse($check['valid']);
        $this->assertSame('conflict',$check['issues'][0]['error']);
    }
    public function test_linked_operations_never_publish_partially(): void
    {
        $d=$this->accepted($this->draft(),[$this->replacement(),['op'=>'replace','entity'=>'step','id'=>1,'field'=>'notes','value'=>'Новые заметки.']]);
        DB::table('tasks')->where('id',1)->update(['text'=>'Внешнее изменение.']);
        try { $this->publish($d); $this->fail('Expected conflict'); } catch (Problem $e) { $this->assertSame('publication_blocked',$e->error); }
        $this->assertSame('Приватные заметки.',DB::table('program_steps')->where('id',1)->value('notes'));
        $this->assertDatabaseCount('material_review_publications',0);
    }
    public function test_full_alternatives_cannot_implicitly_remove_tasks_or_reset_settings(): void
    {
        $d=$this->draft(); $target=$d['targets']; $target[0]['fields']['name']='Другая последовательность';
        $alternative=$this->draft([1],['targets'=>$target,'title'=>'Альтернатива']);
        $this->publish($alternative); $this->assertSame('Другая последовательность',DB::table('lessons')->where('id',1)->value('name'));
        $target[0]['steps'][0]['tasks']=[];
        $this->expectException(Problem::class); $this->draft([1],['targets'=>$target]);
    }
    public function test_notebook_edit_preserves_other_cells_metadata_and_attachments(): void
    {
        $book=['nbformat'=>4,'nbformat_minor'=>5,'metadata'=>['keep'=>'yes'],'cells'=>[
            ['id'=>'m','cell_type'=>'markdown','metadata'=>['x'=>1],'attachments'=>['a'=>['image/png'=>'AA==']],'source'=>['Текст']],
            ['id'=>'c','cell_type'=>'code','metadata'=>[],'source'=>['print(1)'],'execution_count'=>1,'outputs'=>[['output_type'=>'stream','name'=>'stdout','text'=>'1']]],
            ['id'=>'c2','cell_type'=>'code','metadata'=>[],'source'=>['print(3)'],'execution_count'=>2,'outputs'=>[['output_type'=>'stream','name'=>'stdout','text'=>'3']]],
        ]];
        DB::table('program_steps')->where('id',1)->update(['is_notebook'=>1,'theory'=>Documents::encode($book)]);
        $d=$this->accepted($this->draft(),[['op'=>'notebook_cell','entity'=>'step','id'=>1,'field'=>'theory','cell_id'=>'c','old'=>'print(1)','value'=>'print(2)']]);
        $new=Documents::decode($d['targets'][0]['steps'][0]['fields']['theory']);
        $this->assertSame($book['metadata'],$new['metadata']); $this->assertSame($book['cells'][0],$new['cells'][0]);
        $this->assertSame([],$new['cells'][1]['outputs']); $this->assertNull($new['cells'][1]['execution_count']); $this->assertSame($book['cells'][2],$new['cells'][2]);
    }
    public function test_archive_preserves_attempts_and_excludes_step_from_route(): void
    {
        $base=$this->review->documents->lesson(1); $fates=[]; foreach ($base['steps'][0]['tasks'] as $t) $fates[$t['id']]='hide';
        $before=DB::table('solutions')->count(); $d=$this->accepted($this->draft(),[['op'=>'archive_step','id'=>1,'tasks'=>$fates]]); $this->publish($d);
        $this->assertNotNull(DB::table('program_steps')->where('id',1)->value('review_archived_at'));
        $this->assertFalse(\App\Lesson::find(1)->steps->contains('id',1));
        $this->assertSame($before,DB::table('solutions')->count()); $this->assertSame(1,(int)DB::table('tasks')->where('id',1)->value('is_hidden'));
    }
    public function test_new_entities_receive_permanent_ids_once_and_reorder_retains_existing_ids(): void
    {
        $d=$this->accepted($this->draft(),[
            ['op'=>'create_step','lesson_id'=>1,'key'=>'new:intro','after'=>'start','fields'=>['name'=>'Введение','theory'=>'Готовое объяснение.']],
            ['op'=>'move_task','id'=>1,'step_id'=>'new:intro'],
            ['op'=>'create_task','step_id'=>'new:intro','key'=>'new:exercise','fields'=>['name'=>'Новая задача','text'=>'Новое упражнение.','max_mark'=>3]],
        ]);
        $pub=$this->publish($d); $this->assertArrayHasKey('new:intro',$pub['id_map']);
        $this->assertSame($pub['id_map']['new:intro'],DB::table('tasks')->where('id',1)->value('step_id'));
        $this->assertSame('Новая задача',DB::table('tasks')->where('id',$pub['id_map']['new:exercise'])->value('name'));
    }
    public function test_images_are_versioned_sanitized_and_local_to_usage(): void
    {
        DB::table('program_steps')->where('id',1)->update(['theory'=>'![Схема](/same.svg)']);
        $other=DB::table('program_steps')->where('id','!=',1)->first();
        DB::table('program_steps')->where('id',$other->id)->update(['theory'=>'![Схема](/same.svg)']);
        $r=app(Resources::class)->store($this->admin,1,['name'=>'new.svg','mime'=>'image/svg+xml','data'=>base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect width="50" height="50"/></svg>')]);
        $this->assertStringNotContainsString('onload',base64_decode($r['data'])); $this->assertStringNotContainsString('<script',base64_decode($r['data']));
        $d=$this->accepted($this->draft(),[['op'=>'image','entity'=>'step','id'=>1,'field'=>'theory','old'=>'/same.svg','resource_id'=>$r['id']]]); $this->publish($d);
        $this->assertStringContainsString($r['id'],DB::table('program_steps')->where('id',1)->value('theory'));
        $this->assertSame('![Схема](/same.svg)',DB::table('program_steps')->where('id',$other->id)->value('theory'));
    }
    public function test_score_cap_and_inverse_publication_preserve_history_without_rewards(): void
    {
        DB::table('solutions')->where('id',1)->update(['task_id'=>1,'mark'=>8,'raw_mark'=>8]);
        $coins=DB::table('coin_transactions')->count(); $d=$this->accepted($this->draft(),[$this->replacement('max_mark',5)]); $pub=$this->publish($d);
        $this->assertSame(5,DB::table('solutions')->where('id',1)->value('mark')); $this->assertSame($coins,DB::table('coin_transactions')->count());
        $inverse=app(Publishing::class)->reverse($this->admin,$pub['id']); $this->publish($inverse);
        $this->assertSame(8,DB::table('solutions')->where('id',1)->value('mark')); $this->assertDatabaseCount('material_review_publications',2);
    }
    public function test_review_is_unavailable_while_live_editors_and_archived_materials_keep_working(): void
    {
        (require database_path('migrations/2026_11_18_120000_add_quiz_settings_to_tasks.php'))->up();
        $draft = $this->accepted($this->draft(), [$this->replacement()]);
        $draft = $this->review->select($this->admin, $draft['id'], $draft['version']);
        $draftRow = (array) DB::table('material_review_drafts')->find($draft['id']);
        $attempts = DB::table('solutions')->where('task_id', 1)->count();
        $url = '/insider/material-review';

        $this->get($url)->assertNotFound();
        $this->postJson($url.'/drafts', ['lesson_id' => 1])->assertNotFound();
        $this->getJson($url.'/drafts/'.$draft['id'])->assertNotFound();
        $this->postJson($url.'/drafts/'.$draft['id'].'/publish', ['version' => $draft['version']])->assertNotFound();
        $this->postJson($url.'/preview', ['text' => 'Текст'])->assertNotFound();
        $this->assertArrayNotHasKey('materials:review', \Illuminate\Support\Facades\Artisan::all());
        $this->assertSame('Старое условие.', DB::table('tasks')->find(1)->text);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="50" height="50"/></svg>';
        $resource = app(Resources::class)->store($this->admin, 1, ['name' => 'diagram.svg', 'mime' => 'image/svg+xml', 'data' => base64_encode($svg)]);
        $resourceUrl = '/material-resources/'.$resource['id'];
        $theory = "Текущая теория.\n\n![Схема]($resourceUrl)";
        $this->post('/insider/courses/1/steps/1/edit', ['name' => 'Ступень', 'theory' => $theory])->assertRedirect();
        $this->post('/insider/courses/1/tasks/1/edit', ['name' => 'Задача', 'text' => 'Текущее условие.', 'max_mark' => 10, 'answer_mode' => 'manual'])->assertRedirect();

        $this->assertSame($theory, DB::table('program_steps')->find(1)->theory);
        $this->assertSame('Текущее условие.', DB::table('tasks')->find(1)->text);
        $this->assertSame($draftRow, (array) DB::table('material_review_drafts')->find($draft['id']));
        $this->assertSame($attempts, DB::table('solutions')->where('task_id', 1)->count());
        $this->assertDatabaseCount('material_review_proposals', 1);
        $this->assertDatabaseCount('material_review_publications', 0);
        $this->actingAs(User::findOrFail(1))->get($resourceUrl)->assertOk()->assertSee('<rect', false);
    }
    public function test_drafts_are_admin_only_and_foreign_installations_are_rejected(): void
    {
        $d=$this->draft();
        foreach ([1,2] as $id) {
            try { $this->review->get(User::findOrFail($id), $d['id']); $this->fail('Expected access to be denied.'); }
            catch (Problem $e) { $this->assertSame(403, $e->status); }
        }
        $this->expectException(Problem::class); $this->review->create($this->admin,['format_version'=>1,'installation_id'=>'foreign']);
    }
    public function test_question_without_a_concrete_change_cannot_be_accepted(): void
    {
        [$d,$pid]=$this->proposal($this->draft(),[],['kind'=>'question','scope'=>'lesson','intent'=>'question']);
        $this->expectException(Problem::class); $this->review->decide($this->admin,$d['id'],$pid,'accepted',$d['version']);
    }
    public function test_export_import_and_repeated_proposal_are_idempotent(): void
    {
        $d=$this->draft(); $input=['external_id'=>'stable','title'=>'Правка','reason'=>'Уточняем условие.','operations'=>[$this->replacement()]];
        $p=$this->review->propose($this->admin,$d['id'],$input,1); $q=$this->review->propose($this->admin,$d['id'],$input,2);
        $this->assertTrue($q['duplicate']); $this->assertSame($p['proposal_id'],$q['proposal_id']);
        $package=$this->review->export($this->admin,$d['id']); $this->assertSame($d['id'],$this->review->import($this->admin,$package)['id']);
        $this->assertDatabaseCount('material_review_proposals',1);
    }
    public function test_dependencies_alternatives_and_revoked_prerequisites_are_checked(): void
    {
        [$d,$first]=$this->proposal($this->draft(),[$this->replacement()]);
        [$d,$second]=$this->proposal($d,[['op'=>'replace','entity'=>'step','id'=>1,'field'=>'notes','value'=>'Связанные заметки.']],['depends_on'=>[$first],'scope'=>'structure']);
        $d=$this->review->decide($this->admin,$d['id'],$second,'accepted',$d['version']);
        $this->assertFalse($this->review->check($this->admin,$d['id'])['valid']);
        $d=$this->review->decide($this->admin,$d['id'],$first,'accepted',$d['version']);
        $this->assertTrue($this->review->check($this->admin,$d['id'])['valid']);
        $d=$this->review->decide($this->admin,$d['id'],$first,'pending',$d['version']);
        $this->assertFalse($this->review->check($this->admin,$d['id'])['valid']);
        [$d,$third]=$this->proposal($d,[$this->replacement('text','Другой вариант.')],['alternatives'=>[$second]]);
        $d=$this->review->decide($this->admin,$d['id'],$third,'accepted',$d['version']);
        $this->assertContains('alternative',array_column($this->review->check($this->admin,$d['id'])['issues'],'error'));
    }
    public function test_multiple_lessons_commit_together_and_preserve_task_history(): void
    {
        $lessons=DB::table('lessons')->where('program_id',1)->pluck('id')->all(); $other=(int)$lessons[1];
        $step=(int)DB::table('program_steps')->where('lesson_id',$other)->value('id');
        DB::table('solutions')->where('id',1)->update(['task_id'=>1]);
        $d=$this->accepted($this->draft([1,$other]),[['op'=>'move_task','id'=>1,'step_id'=>$step],['op'=>'replace','entity'=>'lesson','id'=>$other,'field'=>'description','value'=>'Добавлена связанная практика.']]);
        $this->publish($d); $this->assertSame($step,DB::table('tasks')->where('id',1)->value('step_id'));
        $this->assertSame(1,DB::table('solutions')->where('id',1)->value('task_id'));
        $this->assertSame('Добавлена связанная практика.',DB::table('lessons')->where('id',$other)->value('description'));
    }
    public function test_transfer_requires_explicit_compatible_field_selection(): void
    {
        $a=$this->accepted($this->draft(),[$this->replacement()]); $b=$this->draft();
        $transfer=$this->review->transfer($this->admin,$b['id'],$a['id'],['task:1:text'],$b['version']);
        $b=$this->review->get($this->admin,$b['id']); $b=$this->review->decide($this->admin,$b['id'],$transfer['proposal_id'],'accepted',$b['version']);
        $this->assertSame('Новое условие.',$b['targets'][0]['steps'][0]['tasks'][0]['fields']['text']);
        $c=$this->accepted($this->draft(),[$this->replacement('text','Несовместимый текст.')]);
        $this->expectException(Problem::class); $this->review->transfer($this->admin,$c['id'],$a['id'],['task:1:text'],$c['version']);
    }
    public function test_incomplete_values_foreign_entities_and_unknown_settings_are_rejected(): void
    {
        $doc=$this->review->documents->lesson(1);
        foreach ([['op'=>'replace','entity'=>'task','id'=>1,'field'=>'text'],['op'=>'fragment','entity'=>'task','id'=>1,'field'=>'text','old'=>'Старое условие.'],['op'=>'replace','entity'=>'task','id'=>999999,'field'=>'text','value'=>'Взлом'],['op'=>'replace','entity'=>'task','id'=>1,'field'=>'price','value'=>0]] as $op) {
            try { app(Plan::class)->apply([$doc],[$op]); $this->fail('Expected explicit validation'); } catch (Problem $e) { $this->assertNotEmpty($e->error); }
        }
        $this->expectException(Problem::class); $this->proposal($this->draft(),[$this->replacement()],['reason'=>'']);
    }
    public function test_new_proposals_reopen_shared_program_progress(): void
    {
        $d=$this->draft(); $d=$this->review->complete($this->admin,$d['id'],$d['version']);
        $this->assertNotNull(DB::table('material_review_progress')->where('lesson_id',1)->value('reviewed_hash'));
        $this->proposal($d,[$this->replacement()]);
        $this->assertNull(DB::table('material_review_progress')->where('lesson_id',1)->value('reviewed_hash'));
    }
    public function test_historical_condition_is_private_and_accessible_after_a_move(): void
    {
        $work=\App\Solution::find(1); $work->material_snapshot=Documents::encode(\App\MaterialReview\Attempts::snapshot(1)); $work->save();
        $this->assertContains('material_snapshot',$work->getHidden());
        $this->actingAs(User::find(1))->get('/insider/material-history/1')->assertOk()->assertSee('Старое условие.')->assertDontSee('Авторское решение.');
        $this->actingAs(User::find(3))->get('/insider/material-history/1')->assertForbidden();
        $this->actingAs($this->admin)->get('/insider/material-history/1')->assertOk()->assertSee('Авторское решение.');
    }
    public function test_stale_student_page_is_detected_without_creating_a_solution(): void
    {
        $task=\App\Task::find(1); $token=\App\MaterialReview\Attempts::token($task);
        $task->text='Изменённое условие.'; $task->save();
        $count=DB::table('solutions')->count();
        $this->postJson('/insider/courses/1/tasks/1/solution',['text'=>'Несохранённая работа','material_revision'=>$token])->assertStatus(409)->assertJsonPath('error','material_changed');
        $this->assertSame($count,DB::table('solutions')->count());
    }
    public function test_inverse_never_overwrites_a_later_grade(): void
    {
        DB::table('solutions')->where('id',1)->update(['task_id'=>1,'mark'=>8,'raw_mark'=>8]);
        $pub=$this->publish($this->accepted($this->draft(),[$this->replacement('max_mark',5)]));
        DB::table('solutions')->where('id',1)->update(['mark'=>4]);
        $inverse=app(Publishing::class)->reverse($this->admin,$pub['id']);
        try { $this->publish($inverse); $this->fail('Expected newer grade protection'); } catch (Problem $e) { $this->assertSame('score_conflict',$e->error); }
        $this->assertSame(5,DB::table('tasks')->where('id',1)->value('max_mark'));
        $this->assertSame(4,DB::table('solutions')->where('id',1)->value('mark'));
    }
    public function test_legacy_backup_preserves_full_history_blobs_and_decisions_idempotently(): void
    {
        $path=tempnam(sys_get_temp_dir(),'mr-legacy-test-'); $mapping=tempnam(sys_get_temp_dir(),'mr-mapping-test-');
        try {
            $db=new \SQLite3($path); $db->exec('CREATE TABLE source_files(id TEXT PRIMARY KEY,path TEXT,content BLOB); CREATE TABLE proposal_versions(id TEXT,fingerprint TEXT,lesson TEXT,payload TEXT,imported TEXT); CREATE TABLE choices(sequence INTEGER,id TEXT,fingerprint TEXT,state TEXT,patches TEXT,note TEXT,updated TEXT); CREATE TABLE author_edits(id TEXT,payload TEXT); CREATE TABLE source_versions(id TEXT,content BLOB);');
            $db->exec("INSERT INTO source_files VALUES('file-a','lesson.md','Старое объяснение.'); INSERT INTO choices VALUES(1,'p','v1','accepted','[]','Моя заметка','2026-01-01'); INSERT INTO author_edits VALUES('edit','{\"text\":\"Ручная правка\"}');");
            $p=Documents::encode(['id'=>'p','title'=>'Историческая правка','pairs'=>[['file'=>'file-a','old'=>'Старое объяснение.','new'=>'Новое объяснение.']]]);
            $stmt=$db->prepare('INSERT INTO proposal_versions VALUES(:id,:fp,:lesson,:payload,:imported)'); foreach ([':id'=>'p',':fp'=>'v1',':lesson'=>'legacy:1',':payload'=>$p,':imported'=>'2026-01-01'] as $k=>$v) $stmt->bindValue($k,$v,SQLITE3_TEXT); $stmt->execute();
            $stmt=$db->prepare('INSERT INTO source_versions VALUES(:id,:data)'); $stmt->bindValue(':id','blob'); $stmt->bindValue(':data',"\x00\xffimage",SQLITE3_BLOB); $stmt->execute(); $db->close();
            file_put_contents($mapping,Documents::encode(['source_id'=>'test-reviewer','files'=>['file-a'=>['program_id'=>1,'lesson_id'=>1,'entity'=>'step','id'=>1,'field'=>'theory']]]));
            $hash=hash_file('sha256',$path); $adapter=app(\App\MaterialReview\LegacyImport::class);
            $plan=$adapter->run($this->admin,$path,$mapping,false); $this->assertDatabaseCount('material_review_legacy',0); $this->assertSame('accepted',$plan['proposals'][0]['decision']);
            $imported=$adapter->run($this->admin,$path,$mapping,true); $count=DB::table('material_review_legacy')->count();
            $adapter->run($this->admin,$path,$mapping,true); $this->assertSame($count,DB::table('material_review_legacy')->count()); $this->assertDatabaseCount('material_review_proposals',1);
            $draft=$this->review->get($this->admin,$imported['proposals'][0]['draft_id']); $this->assertSame('accepted',$draft['proposals'][0]['decision']); $this->assertSame('Моя заметка',$draft['proposals'][0]['comment']);
            $raw=DB::table('material_review_legacy')->where('table_name','source_versions')->value('payload'); $this->assertStringContainsString(base64_encode("\x00\xffimage"),$raw);
            $this->assertSame($hash,hash_file('sha256',$path)); $this->assertSame('Старое объяснение.',str_replace(["Первый абзац.\n\n","\n\nПоследний абзац."],'',DB::table('program_steps')->where('id',1)->value('theory')));
            $target = ['program_id'=>1, 'lesson_id'=>1, 'entity'=>'step', 'id'=>1, 'field'=>'theory'];
            $published = DB::table('program_steps')->where('id',1)->value('theory');
            $verified = ['lessons'=>['legacy:1'=>['program_id'=>1,'lesson_id'=>1]], 'versions'=>['p'=>['v1'=>[$target]]],
                'publications'=>['p'=>['v1'=>[$target + ['sha256'=>hash('sha256', $published)]]]]];
            file_put_contents($mapping, Documents::encode($verified));
            $this->assertSame('already_applied', $adapter->run($this->admin,$path,$mapping,false)['proposals'][0]['status']);
            $verified['publications']['p']['v1'][0]['sha256'] = hash('sha256', 'Другая редакция');
            file_put_contents($mapping, Documents::encode($verified));
            $this->assertSame('ready', $adapter->run($this->admin,$path,$mapping,false)['proposals'][0]['status']);
        } finally { @unlink($path); @unlink($mapping); }
    }
    public function test_move_uses_destination_deadline_and_inverse_restores_original_policy(): void
    {
        (require database_path('migrations/2026_11_14_190000_create_deadline_policies.php'))->up();
        $other=(int)DB::table('lessons')->where('program_id',1)->where('id','!=',1)->value('id');
        $step=(int)DB::table('program_steps')->where('lesson_id',$other)->value('id');
        $course=\App\Course::find(1); $service=app(\App\Services\CourseDeadlineService::class);
        $service->savePolicy($course,'task',1,['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-11-25','retained_percent'=>70]]],5);
        $original=\App\Task::find(1)->getDeadline(1)->snapshot();
        $service->savePolicy($course,'lesson',$other,['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-12-01','retained_percent'=>50]]],5);
        $expected=\App\Lesson::find($other); $expected=$service->forLesson($expected,1)->snapshot();
        $d=$this->accepted($this->draft([1,$other]),[['op'=>'move_task','id'=>1,'step_id'=>$step]]);
        $check=$this->review->check($this->admin,$d['id']); $this->assertNotEmpty($check['impact']['deadlines']);
        $pub=$this->publish($d); $moved=\App\Task::find(1)->getDeadline(1)->snapshot(); $this->assertSame($expected['stages'],$moved['stages']);
        $inverse=app(Publishing::class)->reverse($this->admin,$pub['id']); $this->publish($inverse);
        $restored=\App\Task::find(1)->getDeadline(1)->snapshot(); $withoutIds=fn($stages)=>array_map(fn($s)=>array_diff_key($s,['id'=>true]),$stages); $this->assertSame($withoutIds($original['stages']),$withoutIds($restored['stages']));
        $this->assertSame(1,\App\Task::find(1)->step->lesson_id);
    }
    public function test_move_preserves_actual_deadline_when_destination_has_none(): void
    {
        (require database_path('migrations/2026_11_14_190000_create_deadline_policies.php'))->up();
        $other=(int)DB::table('lessons')->where('program_id',1)->where('id','!=',1)->value('id'); $step=(int)DB::table('program_steps')->where('lesson_id',$other)->value('id');
        $course=\App\Course::find(1); $service=app(\App\Services\CourseDeadlineService::class);
        $service->savePolicy($course,'lesson',1,['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-11-25','retained_percent'=>70]]],5);
        $before=\App\Task::find(1)->getDeadline(1)->snapshot();
        $this->publish($this->accepted($this->draft([1,$other]),[['op'=>'move_task','id'=>1,'step_id'=>$step]]));
        $after=\App\Task::find(1)->getDeadline(1)->snapshot(); $withoutIds=fn($stages)=>array_map(fn($s)=>array_diff_key($s,['id'=>true]),$stages); $this->assertSame($withoutIds($before['stages']),$withoutIds($after['stages']));
    }
    public function test_notebook_empty_objects_remain_objects_and_snapshot_settings_inherit(): void
    {
        $json='{"nbformat":4,"nbformat_minor":5,"metadata":{},"cells":[{"id":"a","cell_type":"markdown","metadata":{},"attachments":{},"source":["A"]},{"id":"b","cell_type":"code","metadata":{},"source":["print(1)"],"execution_count":1,"outputs":[]}]}';
        $new=app(Plan::class)->cell($json,['cell_id'=>'a','old'=>'A','value'=>'B']); $new=app(Plan::class)->notebook($json,$new); $book=json_decode($new);
        $this->assertInstanceOf(\stdClass::class,$book->metadata); $this->assertInstanceOf(\stdClass::class,$book->cells[0]->attachments);
        $d=$this->draft(); $targets=$d['targets']; $fields=$targets[0]['steps'][0]['tasks'][0]['fields'];
        $targets[0]['steps'][0]['tasks'][0]['fields']=['text'=>'Полностью новое условие.']; unset($targets[0]['steps'][0]['tasks'][0]['checking']);
        $alternative=$this->draft([1],['targets'=>$targets]); $actual=$alternative['targets'][0]['steps'][0]['tasks'][0]['fields'];
        $this->assertSame($fields['max_mark'],$actual['max_mark']); $this->assertSame($fields['solution'],$actual['solution']); $this->assertSame('Полностью новое условие.',$actual['text']);
    }
    public function test_ordinary_deletion_archives_instead_of_destroying_student_history(): void
    {
        $before=DB::table('solutions')->count(); $task=\App\Task::find(1); $task->delete();
        $this->assertNotNull(\App\Task::find(1)); $this->assertTrue((bool)\App\Task::find(1)->is_hidden);
        $this->get('/insider/courses/1/steps/1/delete')->assertRedirect();
        $this->assertNotNull(DB::table('program_steps')->where('id',1)->value('review_archived_at')); $this->assertSame($before,DB::table('solutions')->count());
    }
    public function test_published_archive_round_trip_preserves_journals_without_reapplying(): void
    {
        $d=$this->accepted($this->draft(),[$this->replacement()]); $pub=$this->publish($d);
        $package=Documents::decode(Documents::encode($this->review->export($this->admin,$d['id'])));
        $this->assertNotEmpty($package['publication_history']); $this->assertNotEmpty($package['revision_history']);
        DB::table('material_review_publications')->where('draft_id',$d['id'])->delete(); DB::table('material_review_proposals')->where('draft_id',$d['id'])->delete(); DB::table('material_review_drafts')->where('id',$d['id'])->delete();
        $restored=$this->review->import($this->admin,$package); $this->assertSame('published',$restored['state']); $this->assertSame($pub['id'],$restored['publication']->id);
        $this->assertSame('Новое условие.',DB::table('tasks')->where('id',1)->value('text'));
        $this->assertTrue(app(Publishing::class)->publish($this->admin,$d['id'],1)['duplicate']);
        $package['draft']['reason']='Изменённый архив'; $this->expectException(Problem::class); $this->review->import($this->admin,$package);
    }
    public function test_restore_archives_new_entities_and_keeps_attempts_created_after_publication(): void
    {
        $d=$this->accepted($this->draft(),[['op'=>'create_step','lesson_id'=>1,'key'=>'new:stage','fields'=>['name'=>'Новая ступень','theory'=>'Готовый материал.']],['op'=>'create_task','step_id'=>'new:stage','key'=>'new:task','fields'=>['name'=>'Новая задача','text'=>'Готовое условие.','max_mark'=>3]]]);
        $pub=$this->publish($d); $task=$pub['id_map']['new:task'];
        $attempt=DB::table('solutions')->insertGetId(['task_id'=>$task,'course_id'=>1,'user_id'=>1,'text'=>'Новая работа','submitted'=>now(),'mark'=>2]);
        $this->publish(app(Publishing::class)->reverse($this->admin,$pub['id']));
        $this->assertNotNull(DB::table('solutions')->find($attempt)); $this->assertTrue((bool)DB::table('tasks')->where('id',$task)->value('is_hidden'));
        $this->assertNotNull(DB::table('program_steps')->where('id',$pub['id_map']['new:stage'])->value('review_archived_at'));
    }
    public function test_clipping_keeps_original_evaluation_and_never_calls_a_model_or_awards_coins(): void
    {
        DB::table('solutions')->where('id',1)->update(['task_id'=>1,'mark'=>8,'raw_mark'=>8]);
        $coins=DB::table('coin_transactions')->count();
        $this->publish($this->accepted($this->draft(),[$this->replacement('max_mark',5)]));
        $this->assertSame(8,DB::table('solutions')->where('id',1)->value('raw_mark'));
        $s=\App\Solution::find(1); $s->mark=9; $s->save(); $this->assertSame(5,(int)$s->mark);
        $this->assertSame($coins,DB::table('coin_transactions')->count()); Http::assertNothingSent(); Queue::assertNothingPushed(); Notification::assertNothingSent();
    }
    public function test_partial_transfer_of_a_linked_full_edition_change_is_blocked(): void
    {
        $base=$this->draft(); $targets=$base['targets']; $targets[0]['steps'][0]['fields']['notes']='Перенесли объяснение.'; $targets[0]['steps'][0]['tasks'][0]['fields']['text']='Связанное новое условие.';
        $source=$this->draft([1],['targets'=>$targets,'transfer_groups'=>[['id'=>'linked','title'=>'Объяснение и задача','reason'=>'Задача опирается на новое объяснение; меняем их вместе.','paths'=>['step:1:notes','task:1:text']]]]);
        try { $this->review->transfer($this->admin,$base['id'],$source['id'],['task:1:text'],$base['version']); $this->fail('Expected logical group validation'); }
        catch (Problem $e) { $this->assertSame('linked_change',$e->error); }
        $transferred=$this->review->transfer($this->admin,$base['id'],$source['id'],['step:1:notes','task:1:text'],$base['version']);
        $fresh=$this->review->get($this->admin,$base['id']); $this->assertCount(1,$fresh['proposals']);
        $fresh=$this->review->decide($this->admin,$base['id'],$transferred['proposal_id'],'accepted',$fresh['version']);
        $this->assertSame('Связанное новое условие.',$fresh['targets'][0]['steps'][0]['tasks'][0]['fields']['text']);
        $this->assertSame('Перенесли объяснение.',$fresh['targets'][0]['steps'][0]['fields']['notes']);
    }
}
