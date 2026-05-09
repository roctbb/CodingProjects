<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddXpBoostersToTasksAndSolutions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'xp_booster_enabled')) {
                $table->boolean('xp_booster_enabled')->default(false)->after('is_hidden');
            }
        });

        Schema::table('solutions', function (Blueprint $table) {
            if (!Schema::hasColumn('solutions', 'xp_booster_amount')) {
                $table->integer('xp_booster_amount')->unsigned()->default(0)->after('deadline_penalty_paid_at');
            }

            if (!Schema::hasColumn('solutions', 'xp_booster_used_at')) {
                $table->dateTime('xp_booster_used_at')->nullable()->after('xp_booster_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            if (Schema::hasColumn('solutions', 'xp_booster_used_at')) {
                $table->dropColumn('xp_booster_used_at');
            }

            if (Schema::hasColumn('solutions', 'xp_booster_amount')) {
                $table->dropColumn('xp_booster_amount');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'xp_booster_enabled')) {
                $table->dropColumn('xp_booster_enabled');
            }
        });
    }
}
