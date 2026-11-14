<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_execution_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('execution_run_id');
            $table->uuid('submission_id');
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('recipient_user_id');
            $table->string('kind', 32);
            $table->string('bot_key', 64);
            $table->string('chat_id', 32);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('message_id', 32)->nullable();
            $table->string('last_error', 100)->nullable();
            $table->timestamps();
            $table->unique(['execution_run_id', 'kind', 'recipient_user_id'], 'code_alert_run_kind_recipient');
            $table->index(['status', 'available_at']);
            $table->index(['status', 'reserved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_execution_alerts');
    }
};
