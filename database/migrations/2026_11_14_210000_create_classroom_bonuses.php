<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_session_resources', fn (Blueprint $t) => $t->unsignedInteger('class_bonus_gc')->default(0));
        foreach (['solutions', 'code_submissions'] as $table) if (Schema::hasTable($table)) Schema::table($table, fn (Blueprint $t) => $t->json('class_bonus_snapshot')->nullable());
        Schema::create('classroom_bonus_awards', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->unsignedInteger('user_id'); $t->unsignedInteger('task_id');
            $t->unsignedInteger('solution_id'); $t->unsignedBigInteger('session_id'); $t->unsignedInteger('amount');
            $t->unsignedInteger('coin_transaction_id')->nullable(); $t->timestamps();
            $t->unique(['course_id', 'user_id', 'task_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('classroom_bonus_awards');
        Schema::table('planner_session_resources', fn (Blueprint $t) => $t->dropColumn('class_bonus_gc'));
        foreach (['solutions', 'code_submissions'] as $table) if (Schema::hasColumn($table, 'class_bonus_snapshot')) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('class_bonus_snapshot'));
    }
};
