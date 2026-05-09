<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddHomepagePerformanceIndexes extends Migration
{
    private $indexes = [
        ['course_students', 'idx_course_students_user_course', 'user_id, course_id'],
        ['course_students', 'idx_course_students_course_user', 'course_id, user_id'],
        ['course_teachers', 'idx_course_teachers_user_course', 'user_id, course_id'],
        ['course_teachers', 'idx_course_teachers_course_user', 'course_id, user_id'],
        ['courses', 'idx_courses_state_mode', 'state, mode'],
        ['course_student_points', 'idx_course_student_points_student_course', 'student_id, course_id'],
        ['solutions', 'idx_solutions_user_course_mark_task', 'user_id, course_id, mark, task_id'],
        ['solutions', 'idx_solutions_course_submitted_mark', 'course_id, submitted, mark'],
        ['task_deadlines', 'idx_task_deadlines_course_expiration_task', 'course_id, expiration, task_id'],
        ['coin_transactions', 'idx_coin_transactions_user', 'user_id'],
        ['completed_courses', 'idx_completed_courses_user_mark', 'user_id, mark'],
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
