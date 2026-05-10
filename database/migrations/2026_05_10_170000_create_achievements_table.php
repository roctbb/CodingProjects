<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAchievementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('achievements')) {
            return;
        }

        Schema::create('achievements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('course_id')->unsigned();
            $table->integer('task_id')->unsigned();
            $table->integer('solution_id')->unsigned()->nullable();
            $table->string('source')->default('ai_task_solution');
            $table->string('status')->default('published');
            $table->string('title', 120);
            $table->text('description');
            $table->string('icon_key', 40)->default('sparkles');
            $table->json('payload')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('solution_id')->references('id')->on('solutions')->onDelete('set null');

            $table->unique(['user_id', 'task_id']);
            $table->index(['course_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('achievements');
    }
}
