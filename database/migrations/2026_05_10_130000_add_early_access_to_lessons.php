<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('lessons', 'early_access_enabled')) {
                $table->boolean('early_access_enabled')->default(false)->after('is_open');
            }
        });

        if (!Schema::hasTable('lesson_early_accesses')) {
            Schema::create('lesson_early_accesses', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('course_id');
                $table->unsignedInteger('lesson_id');
                $table->unsignedInteger('user_id');
                $table->timestamps();

                $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
                $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['course_id', 'lesson_id', 'user_id'], 'lesson_early_access_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('lesson_early_accesses')) {
            Schema::dropIfExists('lesson_early_accesses');
        }

        Schema::table('lessons', function (Blueprint $table) {
            if (Schema::hasColumn('lessons', 'early_access_enabled')) {
                $table->dropColumn('early_access_enabled');
            }
        });
    }
};
