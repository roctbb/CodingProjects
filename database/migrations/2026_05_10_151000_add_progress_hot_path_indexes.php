<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddProgressHotPathIndexes extends Migration
{
    private $indexes = [
        ['solutions', 'idx_solutions_user_task_course', 'user_id, task_id, course_id'],
        ['task_deadlines', 'idx_task_deadlines_task_course', 'task_id, course_id'],
        ['lesson_info', 'idx_lesson_info_lesson_course', 'lesson_id, course_id'],
        ['blocked_tasks', 'idx_blocked_tasks_user_course_task', 'user_id, course_id, task_id'],
    ];

    public function up()
    {
        foreach ($this->indexes as [$table, $name, $columns]) {
            $this->createIndex($table, $name, $columns);
        }
    }

    public function down()
    {
        foreach (array_reverse($this->indexes) as [$table, $name]) {
            $this->dropIndex($table, $name);
        }
    }

    private function createIndex($table, $name, $columns)
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columns})");
            return;
        }

        try {
            DB::statement("CREATE INDEX {$name} ON {$table} ({$columns})");
        } catch (\Throwable $e) {
            // Index already exists on some installations.
        }
    }

    private function dropIndex($table, $name)
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$name}");
            return;
        }

        try {
            DB::statement("DROP INDEX {$name} ON {$table}");
        } catch (\Throwable $e) {
            // Index is already absent.
        }
    }
}
