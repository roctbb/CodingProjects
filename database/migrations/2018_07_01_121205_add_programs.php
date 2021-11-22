<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrograms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->increments('id');
            $table->string("name");
            $table->string("description")->nullable();
            $table->string("image")->nullable();
            $table->timestamps();
        });

        Schema::create('programs_authors', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('program_id')->unsigned()->nullable();
            $table->foreign('program_id')->references('id')
                ->on('programs')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->integer('program_id')->unsigned()->nullable();
            $table->foreign('program_id')->references('id')
                ->on('programs')->onDelete('cascade');
        });

        Schema::rename('course_steps', 'program_steps');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('programs');
    }
}
