<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSdlCourses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_sdl')->default(false);
        });
        Schema::table('lessons', function (Blueprint $table) {

            $table->integer('sdl_node_id')->unsigned()->nullable();
            $table->foreign('sdl_node_id')->references('id')
                ->on('core_nodes')->onDelete('set null');
        });

        Schema::create('sdl_courses_users_lessons', function (Blueprint $table) {

            $table->integer('lesson_id')->unsigned()->nullable();
            $table->foreign('lesson_id')->references('id')
                ->on('lessons')->onDelete('cascade');

            $table->integer('course_id')->unsigned()->nullable();
            $table->foreign('course_id')->references('id')
                ->on('courses')->onDelete('cascade');

            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
