<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('task_chat_messages', function (Blueprint $table) {
            $table->boolean('automatic')->default(false);
        });
        if (!Schema::hasColumn('solutions', 'recheck_comment')) return;
        DB::table('task_chat_messages as m')->join('solutions as s', 's.id', '=', 'm.solution_id')
            ->whereColumn('m.author_id', 'm.student_id')->whereColumn('m.student_id', 's.user_id')
            ->whereColumn('m.task_id', 's.task_id')->whereColumn('m.course_id', 's.course_id')
            ->where('m.body', 'like', "**Запрос на перепроверку**\n\n%")
            ->select('m.id', 'm.body', 's.recheck_comment')->chunkById(200, function ($messages) {
                $ids = $messages->filter(fn ($m) => $m->body === "**Запрос на перепроверку**\n\n".$m->recheck_comment)->pluck('id');
                DB::table('task_chat_messages')->whereIntegerInRaw('id', $ids)->update(['automatic'=>true]);
            }, 'm.id', 'id');
    }

    public function down(): void
    {
        Schema::table('task_chat_messages', fn (Blueprint $table) => $table->dropColumn('automatic'));
    }
};
