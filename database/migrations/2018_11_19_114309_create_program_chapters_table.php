<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProgramChaptersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('program_chapters', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('program_id')->unsigned()->nullable();
            $table->foreign('program_id')->references('id')
                ->on('programs')->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_scale_blocking')->default(false);
            $table->boolean('is_time_blocking')->default(false);

            $table->integer('scale_id')->unsigned()->nullable();
            $table->foreign('scale_id')->references('id')
                ->on('result_scales')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->integer('chapter_id')->unsigned()->nullable();
            $table->foreign('chapter_id')->references('id')
                ->on('program_chapters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('program_chapters');
    }
}
