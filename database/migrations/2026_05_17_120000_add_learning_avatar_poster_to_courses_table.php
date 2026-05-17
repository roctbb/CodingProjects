<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLearningAvatarPosterToCoursesTable extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'learning_avatar_poster')) {
                $table->string('learning_avatar_poster')->nullable()->after('image');
            }

            if (!Schema::hasColumn('courses', 'learning_avatar_poster_prompt')) {
                $table->text('learning_avatar_poster_prompt')->nullable()->after('learning_avatar_poster');
            }

            if (!Schema::hasColumn('courses', 'learning_avatar_poster_generated_at')) {
                $table->timestamp('learning_avatar_poster_generated_at')->nullable()->after('learning_avatar_poster_prompt');
            }
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'learning_avatar_poster_generated_at')) {
                $table->dropColumn('learning_avatar_poster_generated_at');
            }

            if (Schema::hasColumn('courses', 'learning_avatar_poster_prompt')) {
                $table->dropColumn('learning_avatar_poster_prompt');
            }

            if (Schema::hasColumn('courses', 'learning_avatar_poster')) {
                $table->dropColumn('learning_avatar_poster');
            }
        });
    }
}
