<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('kind', 20);
            $table->string('source_key', 64);
            $table->json('payload');
            $table->timestamp('displayed_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id', 'kind', 'source_key']);
            $table->index(['user_id', 'displayed_at', 'id']);
        });

        // Existing collections are a baseline, not a queue of new congratulations.
        DB::table('lesson_student_stats as stats')
            ->join('lessons as lesson', 'lesson.id', '=', 'stats.lesson_id')
            ->join('users as student', 'student.id', '=', 'stats.student_id')
            ->where('student.role', 'student')->where('stats.percent', '>', 90)
            ->whereNotNull('lesson.sticker')->where('lesson.sticker', '<>', '')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('program_steps as step')
                ->join('tasks as task', 'task.step_id', '=', 'step.id')
                ->whereColumn('step.lesson_id', 'lesson.id'))
            ->select(['stats.id', 'stats.student_id', 'lesson.sticker'])
            ->chunkById(500, function ($rows) {
                $now = now();
                DB::table('reward_notices')->insertOrIgnore($rows->map(fn ($row) => [
                    'user_id' => $row->student_id, 'kind' => 'sticker',
                    'source_key' => hash('sha256', $row->sticker), 'payload' => '{}',
                    'displayed_at' => $now, 'created_at' => $now,
                ])->all());
            }, 'stats.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_notices');
    }
};
