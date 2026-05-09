<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropProviderIdFromCoursesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('courses', 'provider_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropForeign(['provider_id']);
                $table->dropColumn('provider_id');
            });
        }

        $completedCourseColumns = [];
        if (Schema::hasColumn('completed_courses', 'provider')) {
            $completedCourseColumns[] = 'provider';
        }
        if (Schema::hasColumn('completed_courses', 'class')) {
            $completedCourseColumns[] = 'class';
        }

        if ($completedCourseColumns) {
            Schema::table('completed_courses', function (Blueprint $table) use ($completedCourseColumns) {
                $table->dropColumn($completedCourseColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn('courses', 'provider_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->integer('provider_id')->nullable();
                $table->foreign('provider_id')->references('id')->on('providers')->onDelete('set null');
            });
        }

        Schema::table('completed_courses', function (Blueprint $table) {
            if (!Schema::hasColumn('completed_courses', 'provider')) {
                $table->string('provider')->default('GeekON-School');
            }

            if (!Schema::hasColumn('completed_courses', 'class')) {
                $table->string('class')->default('secondary');
            }
        });
    }
}
