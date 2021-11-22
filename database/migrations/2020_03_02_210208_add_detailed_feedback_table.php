<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDetailedFeedbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detailed_feedback', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('course_id');
            $table->string("access_key")->nullable();
            $table->boolean('is_filled')->default(false);

            $table->integer('mark')->nullable();

            $table->boolean('is_missed')->default(false);
            $table->boolean('is_late')->default(false);
            $table->boolean('is_conflict')->default(false);
            $table->boolean('is_unprepaired')->default(false);
            $table->boolean('need_communication')->default(false);


            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->timestamps();
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
