<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('market_deals')) Schema::table('market_deals', function (Blueprint $t) {
            $t->uuid('purchase_key')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->unsignedInteger('cancelled_by')->nullable();
            $t->unsignedInteger('refund_transaction_id')->nullable();
            $t->unique(['user_id', 'purchase_key']);
        });
        if (Schema::hasTable('solutions')) {
            Schema::table('solutions', fn (Blueprint $t) => $t->integer('evaluated_mark')->nullable());
            // Recover only unambiguous original grades. Keep existing XP and coin history intact.
            if (Schema::hasColumn('solutions', 'class_bonus_snapshot')) DB::table('solutions')->whereNotNull('class_bonus_snapshot')
                ->orderBy('id')->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $snapshot = json_decode($row->class_bonus_snapshot, true);
                        if (isset($snapshot['graded_raw'])) DB::table('solutions')->where('id', $row->id)
                            ->update(['evaluated_mark' => max(0, (int) $snapshot['graded_raw'])]);
                    }
                });
            if (Schema::hasTable('tasks') && Schema::hasColumn('solutions', 'raw_mark')) DB::table('solutions')
                ->whereNull('evaluated_mark')->where('comment', 'Неверный ответ.')->where('mark', 1)->where('raw_mark', 1)
                ->whereIn('task_id', DB::table('tasks')->where('is_quiz', true)->select('id'))->update(['evaluated_mark' => 0]);
        }
        if (Schema::hasTable('blocked_tasks')) Schema::create('task_block_events', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->unsignedInteger('task_id');
            $t->unsignedInteger('student_id'); $t->unsignedInteger('actor_id');
            $t->string('action'); $t->string('reason')->nullable(); $t->timestamps();
        });
        if (Schema::hasTable('course_students')) {
            // Preserve existing memberships and privacy flags before enforcing their identity.
            $duplicates = DB::table('course_students')->select('course_id', 'user_id')
                ->whereNotNull('course_id')->whereNotNull('user_id')->groupBy('course_id', 'user_id')->havingRaw('COUNT(*) > 1')->get();
            foreach ($duplicates as $pair) {
                $query = DB::table('course_students')->where('course_id', $pair->course_id)->where('user_id', $pair->user_id);
                $rows = $query->orderBy('created_at')->get(); $saved = (array) $rows->first();
                if (array_key_exists('hidden_from_stats', $saved)) $saved['hidden_from_stats'] = $rows->contains(fn ($r) => (bool) $r->hidden_from_stats);
                if (array_key_exists('is_remote', $saved)) $saved['is_remote'] = $rows->every(fn ($r) => (bool) $r->is_remote);
                $query->delete(); DB::table('course_students')->insert($saved);
            }
            Schema::table('course_students', fn (Blueprint $t) => $t->unique(['course_id', 'user_id'], 'course_students_membership_unique'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_students')) Schema::table('course_students', fn (Blueprint $t) => $t->dropUnique('course_students_membership_unique'));
        Schema::dropIfExists('task_block_events');
        if (Schema::hasColumn('solutions', 'evaluated_mark')) Schema::table('solutions', fn (Blueprint $t) => $t->dropColumn('evaluated_mark'));
        if (Schema::hasColumn('market_deals', 'purchase_key')) Schema::table('market_deals', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'purchase_key']);
            $t->dropColumn(['purchase_key', 'cancelled_at', 'cancelled_by', 'refund_transaction_id']);
        });
    }
};
