<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAiAchievementFieldsToTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'generates_ai_achievement')) {
                $table->boolean('generates_ai_achievement')->default(false)->after('xp_booster_enabled');
            }

            if (!Schema::hasColumn('tasks', 'ai_achievement_instruction')) {
                $table->text('ai_achievement_instruction')->nullable()->after('generates_ai_achievement');
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
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'ai_achievement_instruction')) {
                $table->dropColumn('ai_achievement_instruction');
            }

            if (Schema::hasColumn('tasks', 'generates_ai_achievement')) {
                $table->dropColumn('generates_ai_achievement');
            }
        });
    }
}
