<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('task_chat_messages', function (Blueprint $table) {
            $table->foreignId('answer_to')->nullable()->unique()->constrained('task_chat_messages')->cascadeOnDelete();
            $table->uuid('review_run_id')->nullable();
            $table->json('tutor_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('task_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['answer_to']);
            $table->dropUnique(['answer_to']);
            $table->dropColumn(['answer_to', 'review_run_id', 'tutor_details']);
        });
    }
};
