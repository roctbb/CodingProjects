<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLikesTable extends Migration
{
    /**
     * Run the migrations.answers
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_likes', function (Blueprint $table) {
            $table->integer('event_id');
            $table->integer('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_likes');
    }
}
