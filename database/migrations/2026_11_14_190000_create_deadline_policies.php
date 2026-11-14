<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deadline_policies', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id');
            $t->string('target_type', 16); $t->unsignedInteger('target_id');
            $t->string('mode', 16)->default('custom'); $t->string('timezone', 64)->default('Europe/Moscow');
            $t->unsignedInteger('revision')->default(1); $t->boolean('legacy')->default(false);
            $t->unsignedInteger('updated_by')->nullable(); $t->timestamps();
            $t->unique(['course_id', 'target_type', 'target_id']);
        });
        Schema::create('deadline_stages', function (Blueprint $t) {
            $t->id(); $t->foreignId('policy_id')->constrained('deadline_policies')->cascadeOnDelete();
            $t->dateTime('cutoff_at'); $t->decimal('retained_percent', 11, 8); $t->unsignedInteger('position');
            $t->timestamps(); $t->unique(['policy_id', 'cutoff_at']);
        });
        foreach (['solutions', 'code_submissions'] as $table) {
            if (Schema::hasTable($table)) Schema::table($table, fn (Blueprint $t) => $t->json('deadline_rule_snapshot')->nullable());
        }
        // Keep legacy rows for compatibility and rollback; importing never changes marks.
        $seen = [];
        if (Schema::hasTable('task_deadlines')) {
            DB::table('task_deadlines')->orderBy('id')->chunkById(500, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $key = $row->course_id.':'.$row->task_id;
                    if (isset($seen[$key]) || empty($row->expiration)) continue;
                    $seen[$key] = true;
                    $id = DB::table('deadline_policies')->insertGetId(['course_id' => $row->course_id, 'target_type' => 'task',
                        'target_id' => $row->task_id, 'mode' => 'custom', 'timezone' => 'Europe/Moscow', 'revision' => 1,
                        'legacy' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $cutoff = \Carbon\CarbonImmutable::parse($row->expiration, 'Europe/Moscow')->addDay()->utc();
                    DB::table('deadline_stages')->insert(['policy_id' => $id, 'cutoff_at' => $cutoff->format('Y-m-d H:i:s'),
                        'retained_percent' => ($row->penalty ?? 0) * 100, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
                }
            });
        }
        // Freeze the old contract, including the absence of a deadline. Do not regrade anything.
        $rules = DB::table('deadline_policies as p')->join('deadline_stages as s', 's.policy_id', '=', 'p.id')
            ->select('p.*', 's.cutoff_at', 's.retained_percent')->get()->keyBy(fn ($r) => $r->course_id.':'.$r->target_id);
        foreach (['solutions', 'code_submissions'] as $table) {
            if (!Schema::hasTable($table)) continue;
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $rules) {
                foreach ($rows as $row) {
                    if ($table === 'solutions' && empty($row->submitted)) continue;
                    $r = $rules->get($row->course_id.':'.$row->task_id);
                    $snapshot = $r ? ['timezone' => $r->timezone, 'policy_id' => $r->id, 'revision' => 1, 'legacy' => true,
                        'target_type' => 'task', 'target_id' => $row->task_id,
                        'stages' => [['at' => $r->cutoff_at, 'percent' => $r->retained_percent]]] : ['none' => true];
                    DB::table($table)->where('id', $row->id)->update(['deadline_rule_snapshot' => json_encode($snapshot)]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['solutions', 'code_submissions'] as $table) {
            if (Schema::hasColumn($table, 'deadline_rule_snapshot')) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('deadline_rule_snapshot'));
        }
        Schema::dropIfExists('deadline_stages'); Schema::dropIfExists('deadline_policies');
    }
};
