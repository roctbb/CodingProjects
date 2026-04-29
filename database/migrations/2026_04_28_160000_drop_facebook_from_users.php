<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropFacebookFromUsers extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'facebook')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('facebook');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'facebook')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook')->nullable();
        });
    }
}
