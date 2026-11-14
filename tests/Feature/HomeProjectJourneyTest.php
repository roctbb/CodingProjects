<?php

namespace Tests\Feature;

use App\Course;
use App\Services\HomeProjectJourney;
use App\Support\ProjectPath;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeProjectJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, current_lesson_id INTEGER);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, chapter_id INTEGER, name TEXT, sort_index INTEGER, sticker TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, lesson_id INTEGER, sort_index INTEGER, name TEXT DEFAULT 'Step');
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, course_id INTEGER, student_id INTEGER, lesson_id INTEGER, max_points INTEGER, percent REAL);
            INSERT INTO users VALUES (1,'Student','student'),(2,'Teacher','teacher'),(3,'Other','student');
            INSERT INTO courses VALUES (1,1,2),(2,1,2);
            INSERT INTO course_students (course_id,user_id) VALUES (1,1),(2,3);
            INSERT INTO program_chapters VALUES (1,1,'Chapter',1),(2,1,'Later chapter',2);
            INSERT INTO lessons VALUES (1,1,1,'Rewarded',1,'/stickers/1.png'),(2,1,1,'Next project',2,'/stickers/2.png'),(3,1,1,'Reading',3,'/stickers/3.png'),(4,1,1,'Empty',4,NULL),(5,1,2,'Later lesson',5,NULL);
            INSERT INTO program_steps (id,lesson_id,sort_index) VALUES (1,1,1),(2,2,1),(3,3,1),(5,5,1);
            INSERT INTO tasks VALUES (1,1),(2,2);
            INSERT INTO lesson_info (course_id,lesson_id,start_date) VALUES (1,1,'2020-01-01'),(1,3,'2020-01-01');
            INSERT INTO lesson_student_stats (course_id,student_id,lesson_id,max_points,percent) VALUES (1,1,1,10,100),(1,3,2,10,100),(2,1,2,10,100);
            SQL);
    }

    private function journey(?int $user = 1): ?array
    {
        return app(HomeProjectJourney::class)->forStudent(Course::with('teachers')->get(), User::findOrFail($user));
    }

    public function test_locked_pinned_lesson_cannot_be_opened_and_reading_remains_available(): void
    {
        $journey = $this->journey();
        $this->assertSame(3, $journey['current']->id);
        $this->assertFalse($journey['current']->steps->first()->relationLoaded('tasks'));
        $this->assertStringEndsWith('/courses/1/steps/3', $journey['url']);
        $locked = collect($journey['path']['nodes'])->firstWhere('id', 2);
        $this->assertNull($locked['url']);
        $this->assertFalse($locked['earned']);
        $this->assertSame(2, $journey['path']['total']);
    }

    public function test_opened_pinned_lesson_is_current_and_other_students_results_never_count(): void
    {
        DB::table('lesson_info')->insert(['course_id' => 1, 'lesson_id' => 2, 'start_date' => '2020-01-01']);
        $journey = $this->journey();
        $this->assertSame(2, $journey['current']->id);
        $this->assertFalse(collect($journey['path']['nodes'])->firstWhere('id', 2)['earned']);
    }

    public function test_personal_early_access_is_respected_without_opening_the_lesson_for_the_class(): void
    {
        DB::table('lesson_early_accesses')->insert(['course_id' => 1, 'lesson_id' => 2, 'user_id' => 1]);
        $this->assertSame(2, $this->journey()['current']->id);
        $this->assertDatabaseMissing('lesson_info', ['course_id' => 1, 'lesson_id' => 2]);
    }

    public function test_no_journey_is_invented_for_teacher_unenrolled_or_no_open_materials(): void
    {
        $this->assertNull($this->journey(2));
        $this->assertNull(app(HomeProjectJourney::class)->forStudent(Course::whereKey(2)->with('teachers')->get(), User::find(1)));
        DB::table('lesson_info')->delete();
        $this->assertNull($this->journey());
    }

    public function test_long_paths_keep_current_point_and_real_positions_in_a_five_stop_window(): void
    {
        $teacher = User::find(2);
        $course = Course::find(1)->setRelation('teachers', collect([$teacher]));
        $template = \App\Lesson::with('steps')->find(1);
        $chapters = collect(range(1, 30))->map(fn ($id) => (new \App\ProgramChapter())->forceFill(['id' => $id, 'name' => 'Chapter '.$id]));
        $lessons = collect(range(1, 30))->map(fn ($id) => (clone $template)->forceFill(['id' => $id, 'chapter_id' => $id, 'name' => 'Lesson '.$id]));
        $path = ProjectPath::forChapters($chapters, $lessons, $course, $teacher, collect([15 => (object) ['max_points' => 10, 'percent' => 100]]), 15);
        $this->assertSame(30, $path['total']);
        $this->assertSame([13,14,15,16,17], array_column($path['nodes'], 'number'));
        $this->assertSame(15, $path['current']['id']);
        $this->assertFalse($path['current']['earned']);
        $this->assertSame([26,27,28,29,30], array_column(ProjectPath::forChapters($chapters, $lessons, $course, $teacher, collect(), 30)['nodes'], 'number'));
    }

    public function test_path_selects_chapters_and_never_calls_a_partly_closed_chapter_complete(): void
    {
        $journey = $this->journey();
        $node = $journey['path']['nodes'][0];
        $this->assertStringEndsWith('/courses/1?chapter=1', $node['url']);
        $this->assertSame('Chapter', $node['name']);
        $this->assertTrue($node['current']);
        $this->assertFalse($node['earned']);
        $this->assertSame('Later chapter', $journey['path']['nodes'][1]['name']);
        $this->assertNull($journey['path']['nodes'][1]['url']);
    }

    public function test_chapter_reward_mark_requires_results_for_every_lesson(): void
    {
        $course = Course::with('teachers','students')->find(1);
        $lessons = \App\Lesson::with('info','earlyAccesses')->whereIn('id',[1,2])->get();
        $chapters = \App\ProgramChapter::whereKey(1)->get();
        $stats = collect([1=>(object)['max_points'=>10,'percent'=>100],2=>(object)['max_points'=>10,'percent'=>95]]);
        $path = fn () => ProjectPath::forChapters($chapters, $lessons, $course, User::find(1), $stats, 1);
        $this->assertFalse($path()['current']['earned']);
        $lessons[1]->info->push((new \App\LessonInfo())->forceFill(['course_id'=>1,'start_date'=>'2020-01-01']));
        $this->assertTrue($path()['current']['earned']);
        $stats->forget(2);
        $this->assertFalse($path()['current']['earned']);
    }
}
