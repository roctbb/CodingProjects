<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSudentResultsLinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('students_educational_results', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('result_id')->unsigned()->nullable();
            $table->foreign('result_id')->references('id')
                ->on('educational_results')->onDelete('cascade');

            $table->boolean('achieved')->default(false);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('students_scales', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('scale_id')->unsigned()->nullable();
            $table->foreign('scale_id')->references('id')
                ->on('result_scales')->onDelete('cascade');

            $table->integer('level')->default(0);
            $table->text('comment')->nullable();
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
