<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('planner_courses', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('course_id')->unique();
            $t->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('timezone', 64)->default('Europe/Moscow');
            $t->unsignedInteger('default_duration_minutes')->default(90);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });
        Schema::create('planner_schedule_rules', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->string('rule_key', 64);
            $t->unsignedTinyInteger('weekday');
            $t->string('start_time', 5)->nullable();
            $t->unsignedInteger('duration_minutes');
            $t->unsignedInteger('position')->default(0);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['planner_course_id', 'rule_key']);
        });
        Schema::create('planner_calendar_exceptions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('rule_key', 64)->nullable();
            $t->string('reason', 500);
            $t->timestamps();
            $t->index(['planner_course_id', 'starts_on']);
        });
        Schema::create('planner_calendar_slots', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->date('local_date');
            $t->string('start_time', 5)->nullable();
            $t->unsignedInteger('duration_minutes');
            $t->unsignedInteger('position')->default(0);
            $t->string('origin', 16)->default('rule');
            $t->string('rule_key', 64)->nullable();
            $t->string('occurrence_key', 100);
            $t->string('state', 16)->default('available');
            $t->string('reason', 500)->nullable();
            $t->timestamps();
            $t->unique(['planner_course_id', 'occurrence_key']);
            $t->index(['planner_course_id', 'local_date']);
        });
        Schema::create('planner_sessions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->unsignedInteger('calendar_slot_id')->nullable();
            $t->foreign('calendar_slot_id')->references('id')->on('planner_calendar_slots')->nullOnDelete();
            $t->string('title')->default('Занятие');
            $t->text('plan_note')->nullable();
            $t->unsignedInteger('backlog_position')->default(0);
            $t->boolean('pinned')->default(false);
            $t->string('origin', 20)->default('planned');
            $t->timestamps();
            $t->index(['planner_course_id', 'calendar_slot_id']);
            $t->unique('calendar_slot_id');
        });
        Schema::create('planner_session_records', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('session_id')->unique();
            $t->foreign('session_id')->references('id')->on('planner_sessions')->cascadeOnDelete();
            $t->date('held_on');
            $t->string('timezone', 64);
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->text('note')->nullable();
            $t->string('status', 16)->default('draft');
            $t->json('plan_snapshot');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('planner_providers', function (Blueprint $t) {
            $t->increments('id');
            $t->string('key', 80)->unique();
            $t->string('display_name');
            $t->string('adapter_key', 40);
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });
        Schema::create('planner_course_providers', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->unsignedInteger('provider_id');
            $t->foreign('provider_id')->references('id')->on('planner_providers')->restrictOnDelete();
            $t->boolean('enabled')->default(true);
            $t->boolean('show_in_summary')->default(true);
            $t->timestamps();
            $t->unique(['planner_course_id', 'provider_id']);
        });
        Schema::create('planner_resource_refs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('provider_id');
            $t->foreign('provider_id')->references('id')->on('planner_providers')->restrictOnDelete();
            $t->string('resource_type', 80);
            $t->string('external_id', 190);
            $t->string('title', 500);
            $t->text('open_url')->nullable();
            $t->string('source_revision', 190)->nullable();
            $t->json('public_metadata');
            $t->string('state', 20)->default('available');
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
            $t->unique(['provider_id', 'resource_type', 'external_id'], 'planner_resource_identity');
        });
        Schema::create('planner_session_resources', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('session_id');
            $t->foreign('session_id')->references('id')->on('planner_sessions')->cascadeOnDelete();
            $t->unsignedInteger('resource_ref_id');
            $t->foreign('resource_ref_id')->references('id')->on('planner_resource_refs')->restrictOnDelete();
            $t->string('layer', 8)->default('plan');
            $t->unsignedInteger('sort_index')->default(0);
            $t->json('resource_snapshot');
            $t->string('fact_state', 20)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['session_id', 'layer', 'resource_ref_id'], 'planner_session_resource_unique');
        });
        Schema::create('planner_session_texts', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('session_id');
            $t->foreign('session_id')->references('id')->on('planner_sessions')->cascadeOnDelete();
            $t->string('layer', 8)->default('plan');
            $t->unsignedInteger('sort_index')->default(0);
            $t->text('text');
            $t->string('fact_state', 20)->nullable();
            $t->timestamps();
        });
        Schema::create('planner_resource_lesson_links', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('session_resource_id');
            $t->foreign('session_resource_id')->references('id')->on('planner_session_resources')->cascadeOnDelete();
            $t->unsignedInteger('lesson_id');
            $t->string('relation', 12)->default('related');
            $t->string('origin', 24)->default('manual');
            $t->timestamps();
            $t->unique(['session_resource_id', 'lesson_id']);
            $t->index('lesson_id');
        });
        Schema::create('planner_deadlines', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->unsignedInteger('resource_ref_id')->nullable();
            $t->foreign('resource_ref_id')->references('id')->on('planner_resource_refs')->restrictOnDelete();
            $t->string('title', 500);
            $t->date('due_on');
            $t->unsignedInteger('lesson_id')->nullable();
            $t->timestamps();
            $t->unique(['planner_course_id', 'resource_ref_id']);
        });
        Schema::create('planner_changes', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('planner_course_id');
            $t->foreign('planner_course_id')->references('id')->on('planner_courses')->cascadeOnDelete();
            $t->unsignedInteger('actor_id');
            $t->string('operation_id', 80);
            $t->string('operation_type', 80);
            $t->string('request_hash', 64);
            $t->unsignedInteger('revision_after');
            $t->json('delta');
            $t->json('result');
            $t->boolean('undoable')->default(true);
            $t->timestamps();
            $t->unique(['planner_course_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        foreach (['planner_changes', 'planner_deadlines', 'planner_resource_lesson_links', 'planner_session_texts',
            'planner_session_resources', 'planner_resource_refs', 'planner_course_providers', 'planner_providers',
            'planner_session_records', 'planner_sessions', 'planner_calendar_slots', 'planner_calendar_exceptions',
            'planner_schedule_rules', 'planner_courses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
