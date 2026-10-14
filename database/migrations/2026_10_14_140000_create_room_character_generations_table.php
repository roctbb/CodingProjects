<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_character_generations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->uuid('request_key');
            $table->json('request_aliases')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('cost');
            $table->unsignedInteger('charge_transaction_id')->nullable();
            $table->unsignedInteger('refund_transaction_id')->nullable();
            $table->string('source_path')->nullable();
            $table->string('reference_path');
            $table->string('result_path')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
            $table->index(['status', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('room_character_generation_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('room_character_generation_id'));
        Schema::dropIfExists('room_character_generations');
    }
};
