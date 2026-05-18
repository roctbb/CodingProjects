<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCourseActivitiesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('course_activities')) {
            return;
        }

        Schema::create('course_activities', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('course_id')->unsigned()->nullable()->index();
            $table->integer('lesson_id')->unsigned()->nullable()->index();
            $table->integer('step_id')->unsigned()->nullable()->index();
            $table->integer('task_id')->unsigned()->nullable()->index();
            $table->integer('solution_id')->unsigned()->nullable()->index();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->string('type', 64)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_activities');
    }
}
