<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('code_submissions', function (Blueprint $table) {
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
            $table->string('mode', 20)->default('code');
            $table->longText('answer_text')->nullable();
            $table->json('source_metadata')->nullable();
            $table->string('preparation_state', 20)->default('ready');
        });
        Schema::table('code_execution_runs', function (Blueprint $table) {
            $table->longText('comment')->nullable()->change();
            $table->unsignedInteger('graded_by')->nullable();
            $table->timestamp('dispatch_lease_until')->nullable();
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
        });
        Schema::table('code_integrity_reports', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable();
            $table->unsignedInteger('dismissed_by')->nullable();
            $table->text('review_comment')->nullable();
        });
        Schema::create('code_task_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('task_id')->index();
            $table->string('path', 240);
            $table->string('kind', 20)->default('attachment');
            $table->boolean('is_public')->default(true);
            $table->boolean('read_only')->default(false);
            $table->boolean('include_in_check')->default(false);
            $table->json('artifact');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('code_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('revision')->default(1);
            $table->json('value');
            $table->timestamps();
            $table->unique(['user_id', 'course_id', 'task_id']);
        });
        Schema::create('code_legacy_tasks', function (Blueprint $table) {
            $table->string('legacy_id', 64)->primary();
            $table->unsignedInteger('task_id')->index();
            $table->string('definition_hash', 64);
            $table->json('definition');
            $table->timestamp('created_at');
        });
        Schema::create('code_fixture_uploads', function (Blueprint $table) {
            $table->unsignedInteger('task_id');
            $table->string('artifact_id', 64);
            $table->unsignedInteger('uploaded_by');
            $table->timestamp('created_at');
            $table->primary(['task_id', 'artifact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_drafts');
        Schema::dropIfExists('code_legacy_tasks');
        Schema::dropIfExists('code_fixture_uploads');
        Schema::dropIfExists('code_task_files');
        Schema::table('code_submissions', function (Blueprint $table) {
            $table->dropColumn(['mode', 'answer_text', 'source_metadata', 'preparation_state']);
        });
        Schema::table('code_execution_runs', fn (Blueprint $table) => $table->dropColumn(['graded_by', 'dispatch_lease_until']));
        Schema::table('code_integrity_reports', fn (Blueprint $table) => $table->dropColumn(['dismissed_at', 'dismissed_by', 'review_comment']));
    }
};
