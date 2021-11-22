<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCorePrerequisite extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('core_prerequisites', function (Blueprint $table) {

            $table->integer('lesson_id')->unsigned()->nullable();
            $table->foreign('lesson_id')->references('id')
                ->on('lessons')->onDelete('cascade');

            $table->integer('node_id')->unsigned()->nullable();
            $table->foreign('node_id')->references('id')
                ->on('core_nodes')->onDelete('cascade');

            $table->string('level')->default('use');
        });

        Schema::create('core_consequences', function (Blueprint $table) {

            $table->integer('task_id')->unsigned()->nullable();
            $table->foreign('task_id')->references('id')
                ->on('tasks')->onDelete('cascade');

            $table->integer('node_id')->unsigned()->nullable();
            $table->foreign('node_id')->references('id')
                ->on('core_nodes')->onDelete('cascade');

            $table->string('level')->default('use');
            $table->integer('brightness')->default(5);
            $table->integer('cutscore')->default(2);
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
