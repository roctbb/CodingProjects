<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEducationalResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('educational_results', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('level')->default(6);

            $table->integer('scale_id')->unsigned()->nullable();
            $table->foreign('scale_id')->references('id')
                ->on('result_scales')->onDelete('cascade');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('result_id')->unsigned()->nullable();
            $table->foreign('result_id')->references('id')
                ->on('educational_results')->onDelete('cascade');

            $table->boolean('is_demo')->default(false);
            $table->boolean('is_training')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('educational_results');
    }
}
