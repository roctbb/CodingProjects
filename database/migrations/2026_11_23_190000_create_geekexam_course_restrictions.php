<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('geekexam_course_restrictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamp('released_at')->nullable();
            $table->index(['user_id', 'released_at']);
            $table->index(['released_at', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geekexam_course_restrictions');
    }
};
