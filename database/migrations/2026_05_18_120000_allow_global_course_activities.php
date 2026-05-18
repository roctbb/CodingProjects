<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowGlobalCourseActivities extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('course_activities') || !Schema::hasColumn('course_activities', 'course_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE course_activities MODIFY course_id INT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE course_activities ALTER COLUMN course_id DROP NOT NULL');
        }
    }

    public function down()
    {
        if (!Schema::hasTable('course_activities') || !Schema::hasColumn('course_activities', 'course_id')) {
            return;
        }

        DB::table('course_activities')->whereNull('course_id')->delete();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE course_activities MODIFY course_id INT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE course_activities ALTER COLUMN course_id SET NOT NULL');
        }
    }
}
