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
        $this->actingAs(User::findOrFail(1));
    }

    private function settings(array $values = []): array
    {
        return array_merge(['name' => 'Practice', 'description' => 'Description', 'chapter' => 1], $values);
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
            ['//example.org/sticker.png'], ['not a link'], ['https://example.org/'.str_repeat('a', 240)], [['url' => 'https://example.org/a.png']],
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
