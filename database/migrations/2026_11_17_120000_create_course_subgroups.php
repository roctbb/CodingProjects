<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_subgroups', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('course_id');
            $t->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $t->string('name', 100);
            $t->unsignedInteger('current_lesson_id')->nullable();
            $t->boolean('current_lesson_override')->default(false);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
            $t->unique(['course_id', 'name']);
        });
        Schema::create('course_subgroup_memberships', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('course_id');
            $t->unsignedInteger('subgroup_id');
            $t->unsignedInteger('user_id');
            // Nullable unique key permits history and enforces one current subgroup per course.
            $t->unsignedInteger('active_user_id')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $t->foreign('subgroup_id')->references('id')->on('course_subgroups')->cascadeOnDelete();
            $t->unique(['course_id', 'active_user_id'], 'subgroup_one_current_membership');
            $t->index(['course_id', 'user_id', 'started_at'], 'subgroup_membership_history');
        });
        Schema::create('course_subgroup_lessons', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('subgroup_id');
            $t->unsignedInteger('lesson_id');
            $t->dateTime('start_date')->nullable();
            $t->timestamps();
            $t->foreign('subgroup_id')->references('id')->on('course_subgroups')->cascadeOnDelete();
            $t->unique(['subgroup_id', 'lesson_id']);
        });
        // Audience 0 is the existing whole-course calendar/policy; positive IDs are subgroups.
        Schema::table('planner_courses', function (Blueprint $t) {
            if (Schema::hasIndex('planner_courses', 'planner_courses_course_id_unique')) $t->dropUnique(['course_id']);
            $t->unsignedInteger('audience_id')->default(0);
            $t->unique(['course_id', 'audience_id']);
        });
        Schema::table('deadline_policies', function (Blueprint $t) {
            $t->dropUnique(['course_id', 'target_type', 'target_id']);
            $t->unsignedInteger('audience_id')->default(0);
            $t->unique(['course_id', 'audience_id', 'target_type', 'target_id'], 'deadline_policy_audience_target');
        });
    }

    public function down(): void
    {
        // Group-specific calendars and policies must be explicitly migrated before rollback.
        if (\Illuminate\Support\Facades\DB::table('planner_courses')->where('audience_id', '>', 0)->exists()
            || \Illuminate\Support\Facades\DB::table('deadline_policies')->where('audience_id', '>', 0)->exists()) {
            throw new \RuntimeException('Сначала перенесите расписания и сроки подгрупп.');
        }
        Schema::table('deadline_policies', function (Blueprint $t) {
            $t->dropUnique('deadline_policy_audience_target');
            $t->dropColumn('audience_id');
            $t->unique(['course_id', 'target_type', 'target_id']);
        });
        Schema::table('planner_courses', function (Blueprint $t) {
            $t->dropUnique(['course_id', 'audience_id']);
            $t->dropColumn('audience_id');
            $t->unique('course_id');
        });
        Schema::dropIfExists('course_subgroup_lessons');
        Schema::dropIfExists('course_subgroup_memberships');
        Schema::dropIfExists('course_subgroups');
    }
};
