<?php

namespace Tests\Unit;

use App\Course;
use App\Lesson;
use App\Program;
use App\ProgramChapter;
use App\ProgramStep;
use App\Support\CourseStudyOutcome;
use App\User;
use Mockery;
use Tests\TestCase;

class CourseStudyOutcomeTest extends TestCase
{
    private function outcome(array $states, array $access = [true, true], string $state = 'started', int $selected = 0, bool $lockedLesson = false): ?array
    {
        $course = (new Course())->forceFill(['id' => 133, 'state' => $state]);
        $viewer = (new User())->forceFill(['id' => 1, 'role' => 'student']);
        $course->setRelation('teachers', collect());
        $chapters = collect($access)->map(function ($available, $index) use ($course, $viewer) {
            $chapter = Mockery::mock(ProgramChapter::class)->makePartial();
            $chapter->forceFill(['id' => $index + 10, 'name' => 'Глава '.$index]);
            $chapter->shouldReceive('isAvailableForUser')->with($course, $viewer)->andReturn($available);
            return $chapter;
        });
        $program = new Program();
        $program->setRelation('chapters', $chapters);
        $course->setRelation('program', $program);
        $step = (new ProgramStep())->forceFill(['id' => 2510]);
        $lesson = new Lesson();
        $lesson->setRelation('steps', collect([$step]));
        $programLessons = collect();
        if ($lockedLesson) {
            $closed = Mockery::mock(Lesson::class)->makePartial();
            $closed->forceFill(['chapter_id' => $chapters[$selected]->id]);
            $closed->setRelation('steps', collect([$step]));
            $closed->shouldReceive('isAvailableForUser')->with($course, $viewer)->andReturn(false);
            $programLessons->push($closed);
        }
        $program->setRelation('lessons', $programLessons);
        return CourseStudyOutcome::forChapter($course, $chapters[$selected], $viewer, collect([$lesson]), collect($states)->map(fn ($item) => $item + ['nextStep' => $step]));
    }

    public function test_partial_or_reading_only_chapter_is_not_marked_complete(): void
    {
        $this->assertNull($this->outcome([['hasTasks' => true, 'rewardEarned' => false]]));
        $this->assertNull($this->outcome([['hasTasks' => false, 'rewardEarned' => false]]));
    }

    public function test_finished_chapter_offers_the_available_next_chapter(): void
    {
        $outcome = $this->outcome([['hasTasks' => true, 'rewardEarned' => true, 'allTasksDone' => true, 'needsImprovement' => false]]);
        $this->assertSame('Глава пройдена', $outcome['title']);
        $this->assertSame('Следующая глава', $outcome['action']);
        $this->assertStringEndsWith('?chapter=11', $outcome['url']);
    }

    public function test_a_chapter_with_unopened_lessons_is_not_announced_as_finished(): void
    {
        $this->assertNull($this->outcome([['hasTasks' => true, 'rewardEarned' => true, 'allTasksDone' => true, 'needsImprovement' => false]], lockedLesson: true));
    }

    public function test_accepted_tasks_can_finish_a_chapter_without_earning_a_sticker():void
    {
        $state=['hasTasks'=>true,'rewardEarned'=>false,'allTasksDone'=>false,'completed'=>true,'canImprove'=>true];
        $outcome=$this->outcome([$state]);
        $this->assertSame('Глава пройдена',$outcome['title']);
        $this->assertSame('Следующая глава',$outcome['action']);
        $this->assertSame('Улучшить результат',$this->outcome([$state],[true,false])['action']);
    }

    public function test_rewarded_but_incomplete_answers_offer_improvement_when_next_is_locked(): void
    {
        $outcome = $this->outcome([['hasTasks' => true, 'rewardEarned' => true, 'allTasksDone' => false, 'needsImprovement' => true]], [true, false]);
        $this->assertSame('Наклейки главы получены', $outcome['title']);
        $this->assertSame('Улучшить результат', $outcome['action']);
        $this->assertStringContainsString('Следующая глава пока не открыта.', $outcome['text']);
        $this->assertStringEndsWith('/steps/2510', $outcome['url']);
    }

    public function test_last_chapter_and_ended_course_offer_review_without_a_dead_end(): void
    {
        $last = $this->outcome([['hasTasks' => true, 'rewardEarned' => true, 'allTasksDone' => true, 'needsImprovement' => false]], selected: 1);
        $this->assertStringContainsString('последняя глава', $last['text']);
        $this->assertSame('Повторить материалы', $last['action']);
        $ended = $this->outcome([], state: 'ended');
        $this->assertSame('Курс завершён', $ended['title']);
        $this->assertSame('Повторить материалы', $ended['action']);
        $this->assertStringEndsWith('/steps/2510', $ended['url']);
    }
}
