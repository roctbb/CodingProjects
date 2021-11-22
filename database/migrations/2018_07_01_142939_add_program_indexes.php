<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProgramIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->integer('program_id')->unsigned()->nullable();
            $table->foreign('program_id')->references('id')
                ->on('programs')->onDelete('cascade');
        });

        Schema::table('program_steps', function (Blueprint $table) {
            $table->integer('program_id')->unsigned()->nullable();
            $table->foreign('program_id')->references('id')
                ->on('programs')->onDelete('cascade');
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
