<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCore extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('core_nodes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('description')->nullable();
            $table->boolean('is_root')->default(false);
            $table->string('type')->nullable();
        });

        Schema::create('core_edges', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('from_id')->unsigned()->nullable();
            $table->foreign('from_id')->references('id')
                ->on('core_nodes')->onDelete('cascade');

            $table->integer('to_id')->unsigned()->nullable();
            $table->foreign('to_id')->references('id')
                ->on('core_nodes')->onDelete('cascade');

            $table->string('type')->default('partOf');
            $table->string('description')->nullable();


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
