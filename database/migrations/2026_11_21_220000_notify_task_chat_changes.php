<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TABLES = [
        'task_chat_messages' => 'body, submission_id, solution_id',
        'solutions' => 'submitted, checked, mark, comment, recheck_requested, recheck_comment',
        'code_submissions' => 'current_run_id, solution_id',
        'code_execution_runs' => 'state, points, comment, finished_at',
        'code_test_reports' => 'report',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION notify_task_chat_change() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                entry record;
                room text;
            BEGIN
                IF TG_OP = 'DELETE' THEN entry := OLD; ELSE entry := NEW; END IF;
                IF TG_TABLE_NAME = 'code_execution_runs' THEN
                    SELECT concat_ws('/', course_id, task_id, user_id) INTO room
                    FROM code_submissions WHERE id = entry.submission_id;
                ELSIF TG_TABLE_NAME = 'task_chat_messages' THEN
                    room := concat_ws('/', entry.course_id, entry.task_id, entry.student_id);
                ELSE
                    room := concat_ws('/', entry.course_id, entry.task_id, entry.user_id);
                END IF;
                IF room IS NOT NULL THEN PERFORM pg_notify('task_chat_changed', room); END IF;
                RETURN NULL;
            END;
            $$
        SQL);
        foreach (self::TABLES as $table => $columns) {
            DB::unprepared("CREATE TRIGGER task_chat_change AFTER INSERT OR UPDATE OF $columns OR DELETE ON $table
                FOR EACH ROW EXECUTE FUNCTION notify_task_chat_change()");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        foreach (array_keys(self::TABLES) as $table) DB::statement("DROP TRIGGER IF EXISTS task_chat_change ON $table");
        DB::statement('DROP FUNCTION IF EXISTS notify_task_chat_change()');
    }
};
