<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('school')->nullable();
            $table->integer('grade_year')->nullable();
            $table->date('birthday')->nullable();
            $table->text('hobbies')->nullable();
            $table->text('interests')->nullable();
            $table->string('git')->nullable();
            $table->string('vk')->nullable();
            $table->string('facebook')->nullable();
            $table->string('telegram')->nullable();
            $table->text('comments')->nullable();
            $table->text('letter')->nullable();
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
