<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_artifact_contents', function (Blueprint $table) {
            $table->string('id',64)->primary();
            $table->longText('content');
            $table->string('encoding',8)->default('utf8');
        });
        Schema::table('code_artifacts', function (Blueprint $table) {
            $table->string('storage_disk',64)->nullable();
            $table->string('storage_key',512)->nullable();
            $table->timestamp('verified_at')->nullable();
        });
        Schema::table('code_task_files', function (Blueprint $table) {
            $table->text('original_name')->nullable();
            $table->string('mime_type',255)->nullable();
            $table->uuid('previous_id')->nullable();
        });
        Schema::table('code_submissions', function (Blueprint $table) {
            $table->string('legacy_id',64)->nullable()->unique();
            $table->boolean('teacher_viewed')->default(false);
            $table->boolean('is_public')->default(false);
        });
        Schema::table('code_execution_runs', function (Blueprint $table) {
            $table->json('legacy_report')->nullable();
        });
        Schema::create('code_legacy_exports', function (Blueprint $table) {
            $table->string('id',64)->primary();$table->json('manifest');$table->json('files');
            $table->unsignedInteger('created_by');$table->timestamp('created_at');
        });
        Schema::create('code_legacy_task_versions', function (Blueprint $table) {
            $table->string('id',64)->primary();
            $table->string('legacy_id',64)->index();
            $table->json('definition');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_legacy_task_versions');
        Schema::dropIfExists('code_legacy_exports');
        Schema::table('code_execution_runs', fn (Blueprint $table) => $table->dropColumn('legacy_report'));
        Schema::table('code_submissions', function (Blueprint $table) {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['legacy_id','teacher_viewed','is_public']);
        });
        Schema::table('code_task_files', fn (Blueprint $table) => $table->dropColumn(['original_name','mime_type','previous_id']));
        Schema::table('code_artifacts', fn (Blueprint $table) => $table->dropColumn(['storage_disk','storage_key','verified_at']));
        Schema::dropIfExists('code_artifact_contents');
    }
};
