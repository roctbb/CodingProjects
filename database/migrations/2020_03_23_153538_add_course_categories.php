<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCourseCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('course_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 512);
            $table->string('short_description', 512)->nullable();
            $table->text('description')->nullable();

            $table->string('card_image_url', 512)->nullable();
            $table->string('head_image_url', 512)->nullable();
            $table->string('small_image_url', 512)->nullable();
            $table->string('video_url', 512)->nullable();

            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('mode')->default('private');
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
