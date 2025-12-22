<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLessonStudentStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_student_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('course_id')->unsigned();
            $table->integer('lesson_id')->unsigned();
            $table->integer('student_id')->unsigned();
            $table->integer('points')->unsigned()->default(0);
            $table->integer('max_points')->unsigned()->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')
                ->on('courses')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')
                ->on('lessons')->onDelete('cascade');
            $table->foreign('student_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->unique(['course_id', 'lesson_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lesson_student_stats');
    }
}
