<?php

namespace Tests\Feature;

use App\Lesson;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonStickerSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Cache::flush();
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, sort_index INTEGER DEFAULT 0);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, chapter_id INTEGER, name TEXT, description TEXT,
                sticker TEXT, sort_index INTEGER DEFAULT 0, is_open INTEGER DEFAULT 0, early_access_enabled INTEGER DEFAULT 0,
                created_at TEXT, updated_at TEXT);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER, name TEXT,
                sort_index INTEGER DEFAULT 0, theory TEXT, notes TEXT, start_date TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, sort_index INTEGER DEFAULT 0);
            CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, student_id INTEGER, percent INTEGER);
            INSERT INTO users VALUES (1, 'Admin', 'admin', '2026-01-01'), (2, 'Student', 'student', '2026-01-01');
            INSERT INTO programs VALUES (1, 'Program');
            INSERT INTO program_chapters (id, program_id, name) VALUES (1, 1, 'Chapter');
            INSERT INTO courses VALUES (1, 1, 'Course'), (2, 1, 'Shared program course'), (3, 2, 'Other program');
            INSERT INTO lessons (id, program_id, chapter_id, name, description, sticker)
                VALUES (1, 1, 1, 'Practice', 'Description', '/stickers/1.png');
            INSERT INTO program_steps (id, program_id, lesson_id, name) VALUES (1, 1, 1, 'Step');
            INSERT INTO tasks (id, step_id) VALUES (1, 1);
            INSERT INTO lesson_student_stats VALUES (1, 1, 1, 2, 100), (2, 2, 1, 2, 100);
            SQL);
        DB::table('lessons')->insert(['id'=>10, 'program_id'=>2, 'name'=>'Введение в ML',
            'sticker'=>'https://storage.test/file/grade9-2026-01-01-alisa-selezneva-57976fafd9a9.png']);
        (require database_path('migrations/2026_11_21_230000_create_stickers_catalog.php'))->up();
        $this->assertDatabaseHas('stickers', ['name'=>'Алиса Селезнёва','collection'=>'Тайна третьей планеты']);
        DB::table('lessons')->where('id', 10)->delete();
        foreach (['https://storage.geekclass.ru/files/custom.png?download=1&v=2','https://storage.geekclass.ru/files/my-sticker.png'] as $image) {
            \App\Sticker::create(['image'=>$image, 'name'=>'Наклейка', 'collection'=>'Пробная коллекция']);
        }
        $this->actingAs(User::findOrFail(1));
    }

    private function settings(array $values = []): array
    {
        return array_merge(['name' => 'Practice', 'description' => 'Description', 'chapter' => 1], $values);
    }

    public function test_album_distinguishes_available_and_future_rewards_in_the_students_course(): void
    {
        DB::statement('CREATE TABLE lesson_early_accesses (course_id INTEGER, lesson_id INTEGER, user_id INTEGER)');
        foreach ([2, 3, 4] as $id) {
            $image = $id === 4 ? '/stickers/2.png' : "/stickers/$id.png";
            DB::table('lessons')->insert(['id'=>$id,'program_id'=>1,'name'=>"Урок $id",'sticker'=>$image]);
            DB::table('program_steps')->insert(['id'=>$id,'lesson_id'=>$id,'program_id'=>1]);
            DB::table('tasks')->insert(['step_id'=>$id]);
        }
        DB::table('lesson_info')->insert(['lesson_id'=>2, 'course_id'=>1, 'start_date'=>'2020-01-01']);
        $student = User::findOrFail(2);
        $student->setRelation('courses', collect([(new \App\Course)->forceFill(['id'=>1, 'program_id'=>1, 'state'=>'started'])]));
        $service = app(\App\Queries\StickerAlbum::class);
        $slots = $service->forProfile($student, true)->collapse()->keyBy('image');
        $this->assertCount(3, $slots);
        $this->assertSame('earned', $slots['/stickers/1.png']['state']);
        $this->assertSame('available', $slots['/stickers/2.png']['state']);
        $this->assertStringEndsWith('/courses/1/steps/2', $slots['/stickers/2.png']['href']);
        $this->assertSame('locked', $slots['/stickers/3.png']['state']);
        $this->assertArrayNotHasKey('href', $slots['/stickers/3.png']);
        $this->assertCount(1, $service->forProfile($student, false)->collapse());
    }

    public function test_catalog_registration_assignment_and_collection_edit_preserve_one_reward(): void
    {
        $data = ['name'=>'Сова', 'collection'=>'Винни-Пух', 'image'=>'https://example.test/owl.png'];
        $this->post('/insider/settings/stickers', $data)->assertRedirect();
        $sticker = \App\Sticker::where('image', $data['image'])->firstOrFail();
        $this->postJson('/insider/settings/stickers', $data)->assertJsonValidationErrors('image');
        $this->post('/insider/courses/1/lessons/1/edit', $this->settings(['sticker'=>$sticker->image]))->assertRedirect();
        $this->put('/insider/settings/stickers/'.$sticker->id, ['name'=>'Сова', 'collection'=>'Любимые герои'])->assertRedirect();
        $student = User::findOrFail(2);
        $album = app(\App\Queries\StickerAlbum::class)->forProfile($student, false);
        $this->assertSame([$sticker->image], $student->getStickers()->all());
        $this->assertSame(['Program'], $album->keys()->all());
        $this->assertSame('earned', $album->first()->sole()['state']);
        $this->actingAs($student)->postJson('/insider/settings/stickers', $data)->assertForbidden();
        $this->putJson('/insider/settings/stickers/'.$sticker->id, ['name'=>'X', 'collection'=>'X'])->assertForbidden();
    }

    public function test_custom_url_replaces_earned_sticker_and_invalidates_student_caches(): void
    {
        $student = User::findOrFail(2);
        $this->assertSame(['/stickers/1.png'], $student->getStickers()->all());
        $student->getStickerDescriptions();
        Cache::put('user:99:stickers', 'unrelated', 60);
        $url = 'https://storage.geekclass.ru/files/custom.png?download=1&v=2';

        $this->post('/insider/courses/1/lessons/1/edit', $this->settings(['sticker' => $url]))
            ->assertRedirect('/insider/courses/1?chapter=1');

        $this->assertSame($url, Lesson::findOrFail(1)->sticker);
        $this->assertSame([$url], $student->getStickers()->all());
        $this->assertSame([$url => 'Practice'], $student->getStickerDescriptions());
        $this->assertSame('unrelated', Cache::get('user:99:stickers'));
    }

    public function test_empty_url_disables_sticker_and_removes_it_from_earned_collection(): void
    {
        $student = User::findOrFail(2);
        $student->getStickers();
        $student->getStickerDescriptions();

        $this->post('/insider/courses/1/lessons/1/edit', $this->settings(['sticker' => '']))->assertRedirect();

        $this->assertNull(Lesson::findOrFail(1)->sticker);
        $this->assertSame([], $student->getStickers()->all());
        $this->assertSame([], $student->getStickerDescriptions());
    }

    public function test_omitted_field_and_existing_local_sticker_remain_compatible(): void
    {
        $this->post('/insider/courses/1/lessons/1/edit', $this->settings())->assertRedirect();
        $this->assertSame('/stickers/1.png', Lesson::findOrFail(1)->sticker);
        $this->post('/insider/courses/1/lessons/1/edit', $this->settings(['sticker' => '/stickers/40.png']))->assertRedirect();
        $this->assertSame('/stickers/40.png', Lesson::findOrFail(1)->sticker);
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_urls_are_rejected_on_create_and_edit($value): void
    {
        foreach (['/insider/courses/1/create', '/insider/courses/1/lessons/1/edit'] as $endpoint) {
            $this->postJson($endpoint, $this->settings(['sticker' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('sticker');
        }
        $this->assertDatabaseCount('lessons', 1);
        $this->assertSame('/stickers/1.png', Lesson::findOrFail(1)->sticker);
    }

    public static function invalidUrls(): array
    {
        return [
            ['javascript:alert(1)'], ['data:image/png;base64,AAAA'], ['ftp://example.org/sticker.png'],
            ['//example.org/sticker.png'], ['not a link'], ['https://example.org/unregistered.png'], ['https://example.org/'.str_repeat('a', 240)], [['url' => 'https://example.org/a.png']],
        ];
    }

    #[DataProvider('newStickerValues')]
    public function test_new_lesson_accepts_custom_sticker_or_no_sticker(?string $url): void
    {
        $this->post('/insider/courses/1/create', $this->settings(['sticker' => $url]))->assertRedirect();
        $lesson = Lesson::orderByDesc('id')->first();
        $this->assertSame($url, $lesson->sticker);
        $this->assertCount(1, $lesson->steps);
    }

    public static function newStickerValues(): array
    {
        return [['https://storage.geekclass.ru/files/my-sticker.png'], [null]];
    }

    public function test_old_create_requests_keep_the_default_sticker(): void
    {
        $this->post('/insider/courses/1/create', $this->settings())->assertRedirect();
        $this->assertMatchesRegularExpression('~^/stickers/([1-9]|[1-3][0-9]|40)\.png$~', Lesson::orderByDesc('id')->first()->sticker);
    }

    public function test_students_and_wrong_program_cannot_change_sticker(): void
    {
        $settings = $this->settings(['sticker' => 'https://example.org/custom.png']);
        $this->post('/insider/courses/3/lessons/1/edit', $settings)->assertNotFound();
        $this->actingAs(User::findOrFail(2));
        $this->post('/insider/courses/1/lessons/1/edit', $settings)->assertForbidden();
        $this->assertSame('/stickers/1.png', Lesson::findOrFail(1)->sticker);
    }
}
