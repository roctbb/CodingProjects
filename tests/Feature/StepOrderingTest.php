<?php

namespace Tests\Feature;

use App\Lesson;
use App\ProgramStep;
use App\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StepOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER, sort_index INTEGER, created_at TEXT, updated_at TEXT);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, sort_index INTEGER);
            INSERT INTO users VALUES (1, 'Teacher', 'teacher', '2026-01-01');
            INSERT INTO courses VALUES (1, 1);
            INSERT INTO course_teachers (course_id, user_id) VALUES (1, 1);
            INSERT INTO lessons VALUES (1, 1), (2, 1);
            INSERT INTO program_steps (id, program_id, lesson_id, sort_index) VALUES (90, 1, 2, 100), (91, 1, 2, 500);
            SQL);
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    #[DataProvider('movements')]
    public function test_one_click_moves_exactly_one_position(array $indices, int $stepId, string $direction, array $expected): void
    {
        $this->createSteps($indices);

        $this->get('/insider/courses/1/steps/'.$stepId.'/'.$direction)
            ->assertRedirect('/insider/courses/1/steps/'.$stepId);

        $this->assertSame($expected, Lesson::findOrFail(1)->steps->pluck('id')->all());
        $this->assertSame([90 => 100, 91 => 500], ProgramStep::where('lesson_id', 2)->orderBy('id')->pluck('sort_index', 'id')->all());

        $step = ProgramStep::findOrFail($stepId);
        $position = array_search($stepId, $expected);
        $this->assertSame($expected[$position - 1] ?? null, $step->previousStep()?->id);
        $this->assertSame($expected[$position + 1] ?? null, $step->nextStep()?->id);
    }

    public static function movements(): array
    {
        return [
            'up across a large gap' => [[1 => 100, 2 => 500, 3 => 900], 2, 'lower', [2, 1, 3]],
            'down across a large gap' => [[1 => 100, 2 => 500, 3 => 900], 2, 'upper', [1, 3, 2]],
            'up with consecutive indices' => [[1 => 100, 2 => 101, 3 => 102], 2, 'lower', [2, 1, 3]],
            'down with consecutive indices' => [[1 => 100, 2 => 101, 3 => 102], 1, 'upper', [2, 1, 3]],
            'up within duplicate indices' => [[4 => 100, 3 => 100, 2 => 100, 1 => 100], 3, 'lower', [1, 3, 2, 4]],
            'down within duplicate indices' => [[4 => 100, 3 => 100, 2 => 100, 1 => 100], 2, 'upper', [1, 3, 2, 4]],
            'sort index takes priority over id' => [[1 => 900, 2 => -100, 3 => 500], 3, 'upper', [2, 1, 3]],
            'first step cannot move up' => [[1 => 100, 2 => 500, 3 => 900], 1, 'lower', [1, 2, 3]],
            'last step cannot move down' => [[1 => 100, 2 => 500, 3 => 900], 3, 'upper', [1, 2, 3]],
            'only step moving up' => [[1 => 100], 1, 'lower', [1]],
            'only step moving down' => [[1 => 100], 1, 'upper', [1]],
        ];
    }

    public function test_repeated_clicks_move_one_position_each_and_reverse_the_move(): void
    {
        $this->createSteps([1 => 100, 2 => 100, 3 => 900]);

        foreach (['lower' => [[1, 3, 2], [3, 1, 2]], 'upper' => [[1, 3, 2], [1, 2, 3]]] as $direction => $orders) {
            foreach ($orders as $expected) {
                $this->get('/insider/courses/1/steps/3/'.$direction)->assertRedirect('/insider/courses/1/steps/3');
                $this->assertSame($expected, Lesson::findOrFail(1)->steps->pluck('id')->all());
            }
        }
    }

    private function createSteps(array $indices): void
    {
        foreach ($indices as $id => $sortIndex) {
            DB::table('program_steps')->insert([
                'id' => $id, 'program_id' => 1, 'lesson_id' => 1, 'sort_index' => $sortIndex,
            ]);
        }
    }
}
