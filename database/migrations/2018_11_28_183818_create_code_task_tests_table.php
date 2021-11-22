<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCodeTaskTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('code_task_tests', function (Blueprint $table) {
            $table->increments('id');
            $table->text('input');
            $table->text('output');
            $table->text('is_demo')->default(true);
            $table->text('is_shown')->default(true);
            $table->integer('task_id')->unsigned()->nullable();
            $table->foreign('task_id')->references('id')
                ->on('tasks')->onDelete('cascade');
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
        Schema::dropIfExists('code_task_tests');
    }
}
