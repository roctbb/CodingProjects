<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddLearningAvatarPosterToProgramsTable extends Migration
{
    public function up()
    {
        Schema::table('programs', function (Blueprint $table) {
            if (!Schema::hasColumn('programs', 'learning_avatar_poster')) {
                $table->string('learning_avatar_poster')->nullable()->after('image');
            }

            if (!Schema::hasColumn('programs', 'learning_avatar_poster_prompt')) {
                $table->text('learning_avatar_poster_prompt')->nullable()->after('learning_avatar_poster');
            }

            if (!Schema::hasColumn('programs', 'learning_avatar_poster_generated_at')) {
                $table->timestamp('learning_avatar_poster_generated_at')->nullable()->after('learning_avatar_poster_prompt');
            }
        });

        if (Schema::hasColumn('courses', 'learning_avatar_poster')) {
            $programs = DB::table('programs')
                ->select('id', 'learning_avatar_poster')
                ->get();

            foreach ($programs as $program) {
                if ($program->learning_avatar_poster) {
                    continue;
                }

                $coursePoster = DB::table('courses')
                    ->where('program_id', $program->id)
                    ->whereNotNull('learning_avatar_poster')
                    ->orderByDesc('id')
                    ->first([
                        'learning_avatar_poster',
                        'learning_avatar_poster_prompt',
                        'learning_avatar_poster_generated_at',
                    ]);

                if (!$coursePoster) {
                    continue;
                }

                DB::table('programs')
                    ->where('id', $program->id)
                    ->update([
                        'learning_avatar_poster' => $coursePoster->learning_avatar_poster,
                        'learning_avatar_poster_prompt' => $coursePoster->learning_avatar_poster_prompt,
                        'learning_avatar_poster_generated_at' => $coursePoster->learning_avatar_poster_generated_at,
                    ]);
            }
        }
    }

    public function down()
    {
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'learning_avatar_poster_generated_at')) {
                $table->dropColumn('learning_avatar_poster_generated_at');
            }

            if (Schema::hasColumn('programs', 'learning_avatar_poster_prompt')) {
                $table->dropColumn('learning_avatar_poster_prompt');
            }

            if (Schema::hasColumn('programs', 'learning_avatar_poster')) {
                $table->dropColumn('learning_avatar_poster');
            }
        });
    }
}
