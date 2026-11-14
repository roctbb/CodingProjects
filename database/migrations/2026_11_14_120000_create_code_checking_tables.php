<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_artifacts', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->unsignedBigInteger('bytes');
            $table->timestamp('created_at');
        });
        Schema::create('code_task_checkers', function (Blueprint $table) {
            $table->unsignedInteger('task_id')->primary();
            $table->json('config');
            $table->string('revision', 64);
            $table->boolean('allows_test_reveals')->default(true);
            $table->unsignedInteger('updated_by');
            $table->timestamps();
        });
        Schema::create('code_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('task_id');
            $table->uuid('request_id');
            $table->string('payload_hash', 64);
            $table->string('language', 20);
            $table->json('files');
            $table->uuid('current_run_id');
            $table->unsignedInteger('solution_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id', 'task_id', 'request_id'], 'code_submission_request_unique');
            $table->index(['course_id', 'task_id', 'user_id']);
        });
        Schema::create('code_execution_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->json('snapshot');
            $table->string('state', 20)->default('queued');
            $table->json('progress')->nullable();
            $table->unsignedInteger('progress_sequence')->default(0);
            $table->unsignedInteger('points')->nullable();
            $table->text('comment')->nullable();
            $table->string('result_hash', 64)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('dispatch_attempted_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('code_test_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code_id', 64)->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('task_id');
            $table->unsignedBigInteger('started_at');
            $table->string('payload_hash', 64);
            $table->json('report');
            $table->timestamp('created_at');
        });
        Schema::create('code_test_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('task_id');
            $table->string('test_id', 64);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('coin_transaction_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'task_id', 'test_id'], 'code_test_purchase_unique');
        });
        Schema::create('code_integrity_reports', function (Blueprint $table) {
            $table->uuid('submission_id')->primary();
            $table->string('state', 20)->default('pending');
            $table->json('plagiarism')->nullable();
            $table->json('ai')->nullable();
            $table->timestamps();
        });
        Schema::create('code_fingerprints', function (Blueprint $table) {
            $table->uuid('submission_id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('user_id');
            $table->string('fingerprint', 16);
            $table->primary(['submission_id', 'fingerprint']);
            $table->index(['task_id', 'fingerprint']);
        });
        Schema::create('code_attempt_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('task_id');
            $table->uuid('submission_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
            $table->index(['user_id', 'task_id']);
        });
    }

    public function down(): void
    {
        foreach (['code_attempt_credits', 'code_fingerprints', 'code_integrity_reports', 'code_test_purchases', 'code_test_reports', 'code_execution_runs', 'code_submissions', 'code_task_checkers', 'code_artifacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
