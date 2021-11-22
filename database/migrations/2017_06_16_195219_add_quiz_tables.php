<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuizTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->increments('id');
            $table->text('text')->nullable();
            $table->timestamps();
        });

        Schema::create('questions_variants', function (Blueprint $table) {
            $table->increments('id');
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->integer('question_id')->unsigned()->nullable();
            $table->foreign('question_id')->references('id')
                ->on('questions')->onDelete('cascade');
            $table->timestamps();
        });
        Schema::create('answers', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');
            $table->integer('variant_id')->unsigned()->nullable();
            $table->foreign('variant_id')->references('id')
                ->on('questions_variants')->onDelete('cascade');
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
