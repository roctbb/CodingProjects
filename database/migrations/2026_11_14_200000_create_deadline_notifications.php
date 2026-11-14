<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_notification_settings', function (Blueprint $t) {
            $t->unsignedInteger('course_id')->primary(); $t->boolean('teachers_enabled')->default(true);
            $t->boolean('chat_enabled')->default(true); $t->timestamps();
        });
        Schema::create('course_notification_preferences', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->unsignedInteger('user_id');
            $t->boolean('enabled')->default(false); $t->timestamps(); $t->unique(['course_id', 'user_id']);
        });
        Schema::create('course_telegram_chats', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->string('bot_key', 64); $t->string('chat_id', 32);
            $t->unsignedBigInteger('thread_id')->default(0); $t->string('title');
            $t->string('status', 16)->default('pending'); $t->unsignedInteger('linked_by');
            $t->timestamp('confirmed_at')->nullable(); $t->timestamps();
            $t->unique(['course_id', 'bot_key', 'chat_id', 'thread_id'], 'course_telegram_destination');
        });
        Schema::create('course_telegram_tokens', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->unsignedInteger('user_id');
            $t->string('token_hash', 64)->unique(); $t->string('bot_key', 64);
            $t->dateTime('expires_at'); $t->dateTime('consumed_at')->nullable(); $t->timestamps();
        });
        Schema::create('deadline_notification_events', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->unsignedBigInteger('policy_id');
            $t->unsignedInteger('revision'); $t->unsignedBigInteger('stage_id');
            $t->string('kind', 16); $t->string('event_key', 64)->unique();
            $t->dateTime('scheduled_at')->index(); $t->dateTime('expires_at');
            $t->string('status', 16)->default('pending'); $t->timestamps();
            $t->index(['policy_id', 'revision']);
        });
        Schema::create('deadline_notification_deliveries', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('course_id'); $t->string('delivery_key', 64)->unique();
            $t->string('bot_key', 64); $t->string('recipient_type', 16); $t->unsignedBigInteger('recipient_id');
            $t->string('chat_id', 32); $t->unsignedBigInteger('thread_id')->default(0);
            $t->json('event_ids'); $t->string('status', 16)->default('pending'); $t->unsignedInteger('attempts')->default(0);
            $t->dateTime('available_at')->index(); $t->dateTime('reserved_at')->nullable();
            $t->string('message_id', 32)->nullable(); $t->string('last_error', 100)->nullable(); $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) { $t->string('telegram_user_id', 32)->nullable()->index(); });
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_user_id'));
        foreach (['deadline_notification_deliveries', 'deadline_notification_events', 'course_telegram_tokens', 'course_telegram_chats', 'course_notification_preferences', 'course_notification_settings'] as $name) Schema::dropIfExists($name);
    }
};
