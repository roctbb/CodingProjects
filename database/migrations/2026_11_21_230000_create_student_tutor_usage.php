<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_tutor_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('request_id');
            $table->date('usage_day');
            $table->string('state', 16);
            $table->unsignedBigInteger('known_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();
            $table->unique(['user_id','request_id']);
            $table->index(['user_id','usage_day','state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tutor_usage');
    }
};

