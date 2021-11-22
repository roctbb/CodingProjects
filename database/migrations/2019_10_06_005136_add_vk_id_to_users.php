<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVkIdToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('vk_id')->nullable();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('weekdays')->default("");
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vk_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }
}
