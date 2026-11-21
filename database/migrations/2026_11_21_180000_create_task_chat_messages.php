<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_chat_messages', function (Blueprint $table) {
            $table->id();
            foreach (['course_id' => 'courses', 'task_id' => 'tasks', 'student_id' => 'users', 'author_id' => 'users'] as $column => $parent) {
                $table->unsignedInteger($column);
                $table->foreign($column)->references('id')->on($parent)->cascadeOnDelete();
            }
            $table->uuid('submission_id')->nullable();
            $table->foreign('submission_id')->references('id')->on('code_submissions')->nullOnDelete();
            $table->unsignedInteger('solution_id')->nullable();
            $table->foreign('solution_id')->references('id')->on('solutions')->nullOnDelete();
            $table->uuid('client_id');
            $table->text('body');
            $table->timestamp('created_at', 6);
            $table->unique(['author_id', 'client_id']);
            $table->index(['course_id', 'task_id', 'student_id', 'created_at'], 'task_chat_thread_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_chat_messages');
    }
};
