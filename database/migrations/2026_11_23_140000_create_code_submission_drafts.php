<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_submission_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->unique()->constrained('code_submissions')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('revision');
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_submission_drafts');
    }
};
