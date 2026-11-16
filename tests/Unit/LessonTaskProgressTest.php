<?php

namespace Tests\Unit;

use App\Solution;
use App\Support\LessonTaskProgress;
use App\Task;
use Tests\TestCase;

class LessonTaskProgressTest extends TestCase
{
    private function progress(int $xp, int $evaluation): array
    {
        $task = (new Task())->forceFill(['id'=>11,'max_mark'=>10,'is_star'=>false]);
        $task->setRelation('solutions',collect([(new Solution())->forceFill([
            'id'=>1,'user_id'=>1,'submitted'=>now(),'mark'=>$xp,'evaluated_mark'=>$evaluation,'comment'=>'Проверка',
        ])]));
        return LessonTaskProgress::forStudent($task,1,collect());
    }

    public function test_bonus_points_do_not_replace_a_solved_task(): void
    {
        $row=$this->progress(20,3);
        $this->assertFalse($row['completed']);
        $this->assertSame('revision',$row['status']);
        $this->assertSame(20.0,$row['points']);
    }

    public function test_deadline_deductions_do_not_erase_a_fully_correct_solution(): void
    {
        $row=$this->progress(7,10);
        $this->assertTrue($row['completed']);
        $this->assertSame('done',$row['status']);
        $this->assertSame(7.0,$row['points']);
    }

    public function test_an_accepted_partial_grade_can_be_improved_without_blocking_completion():void
    {
        $row=$this->progress(8,8);
        $this->assertTrue($row['completed']);
        $this->assertSame('improve',$row['status']);
    }

    public function test_graded_quiz_records_without_a_submission_timestamp_are_kept():void
    {
        $task=(new Task())->forceFill(['id'=>11,'max_mark'=>10,'is_star'=>false]);
        $task->setRelation('solutions',collect([(new Solution())->forceFill(['id'=>1,'user_id'=>1,'mark'=>10,'submitted'=>null])]));
        $this->assertTrue(LessonTaskProgress::forStudent($task,1,collect())['completed']);
    }

    public function test_an_additional_pending_review_does_not_erase_completion():void
    {
        $task=(new Task())->forceFill(['id'=>11,'max_mark'=>10,'is_star'=>false]);
        $task->setRelation('solutions',collect([
            (new Solution())->forceFill(['id'=>1,'user_id'=>1,'mark'=>10,'evaluated_mark'=>10,'submitted'=>now()]),
            (new Solution())->forceFill(['id'=>2,'user_id'=>1,'mark'=>null,'submitted'=>now()]),
        ]));
        $row=LessonTaskProgress::forStudent($task,1,collect([2=>true]));
        $this->assertTrue($row['completed']);
        $this->assertTrue($row['pending']);
        $this->assertSame('pending',$row['status']);
    }
}
