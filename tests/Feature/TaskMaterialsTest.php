<?php

namespace Tests\Feature;

use App\CodeTaskFile;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class TaskMaterialsTest extends DeadlineFeatureCase
{
    private function material(string $path, bool $public = true, int $task = 11): CodeTaskFile
    {
        return CodeTaskFile::create(['id' => (string) Str::uuid(), 'task_id' => $task, 'path' => $path,
            'kind' => 'attachment', 'is_public' => $public, 'read_only' => false, 'include_in_check' => false,
            'artifact' => ['bytes' => 4, 'blob_id' => str_repeat('a', 64)], 'created_by' => 2]);
    }

    private function conditionFiles(int $viewer): \DOMXPath
    {
        $response = $this->actingAs(User::find($viewer))->get('/insider/courses/1/steps/1')->assertOk();
        $dom = new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        return new \DOMXPath($dom);
    }

    public function test_public_materials_belong_to_the_condition_card_and_private_files_remain_teacher_only(): void
    {
        $public = $this->material('main.cpp'); $private = $this->material('private-criteria.txt', false);
        $this->material('other-step.txt', task: 21);
        DB::enableQueryLog(); DB::flushQueryLog();
        $student = $this->conditionFiles(1);
        $queries = collect(DB::getQueryLog())->filter(fn ($row) => str_contains($row['query'], 'from "code_task_files"'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries, 'Load materials for the whole step in one query');
        $this->assertSame(1, $student->query('//*[@id="task11"]//section[@data-task-condition="11"]//section[contains(@class,"gc-task-materials")]//a[contains(@href,"'.$public->id.'")]')->length);
        $this->assertSame(0, $student->query('//a[contains(@href,"'.$private->id.'")]')->length);
        $this->assertSame(0, $student->query('//*[@id="task12"]//section[contains(@class,"gc-task-materials")]')->length);
        $this->assertSame(1, $student->query('//section[contains(@class,"gc-task-materials")]')->length);
        $teacher = $this->conditionFiles(2);
        $this->assertSame(1, $teacher->query('//*[@id="task11"]//details//section[contains(@class,"gc-task-materials")]//a[contains(@href,"'.$private->id.'")]')->length);
        $this->assertSame(1, $teacher->query('//section[contains(@class,"gc-task-materials")]')->length);
    }

    public function test_empty_or_deleted_materials_do_not_leave_a_section_in_the_student_card(): void
    {
        $this->material('removed.cpp')->delete(); $this->material('private-only.txt', false);
        $this->assertSame(0, $this->conditionFiles(1)->query('//section[contains(@class,"gc-task-materials")]')->length);
    }
}
