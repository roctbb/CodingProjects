<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStickerToCourse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('sticker')->nullable();
        });

        $lessons = \App\Lesson::get();
        foreach ($lessons as $lesson)
        {
            $lesson->sticker = "/stickers/".random_int(1, 40).".png";
            $lesson->save();
        }
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
