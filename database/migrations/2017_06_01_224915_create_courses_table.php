<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoursesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->increments('id');
            $table->string("name");
            $table->string("invite")->unique()->nullable();
            $table->string("description", 2014)->nullable();
            $table->string("image")->nullable();
            $table->date("start_date")->nullable();
            $table->date("end_date")->nullable();
            $table->string("state")->default("draft");
            $table->string("level")->default("start");
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
        Schema::dropIfExists('courses');
    }
}
