<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHiddenFromStatsToCourseMembers extends Migration
{
    public function up()
    {
        Schema::table('course_students', function (Blueprint $table) {
            $table->boolean('hidden_from_stats')->default(false);
        });

        Schema::table('course_teachers', function (Blueprint $table) {
            $table->boolean('hidden_from_stats')->default(false);
        });
    }

    public function down()
    {
        Schema::table('course_students', function (Blueprint $table) {
            $table->dropColumn('hidden_from_stats');
        });

        Schema::table('course_teachers', function (Blueprint $table) {
            $table->dropColumn('hidden_from_stats');
        });
    }
}
