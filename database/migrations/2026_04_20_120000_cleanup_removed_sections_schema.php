<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove schema artifacts for deleted sections:
     * games, ideas, projects, SDL, articles, forum, events, scales, themes.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->dropColumnsIfExist([
            'courses' => [
                'is_sdl',
                'sdl_core_version',
                'sdl_node_id',
            ],
            'lessons' => [
                'is_sdl',
                'sdl_node_id',
                'scale_id',
            ],
            'course_students' => [
                'idea_id',
                'current_project',
            ],
            'program_chapters' => [
                'scale_id',
                'is_scale_blocking',
            ],
        ]);

        $tables = [
            // Games / Ideas / Projects / SDL
            'games',
            'game_votes',
            'game_comments',
            'game_rewards',
            'ideas',
            'projects',
            'project_students',
            'project_awards',
            'project_ideas',
            'sdl_courses_users_lessons',

            // Articles
            'articles',
            'article_tags',
            'articles_tags',
            'article_comments',
            'article_votes',

            // Forum
            'forum_threads',
            'forum_posts',
            'forum_comments',
            'forum_votes',
            'forum_tags',
            'forum_threads_tags',
            'forum_threads_subscribers',

            // Events
            'events',
            'event_comments',
            'event_orgs',
            'event_likes',
            'event_partis',
            'event_tags',
            'tags',

            // Scales
            'result_scales',
            'educational_results',
            'students_educational_results',
            'students_scales',

            // Themes
            'theme_usings',
            'theme_boughts',
            'themes',
        ];

        $driver = Schema::getConnection()->getDriverName();
        foreach ($tables as $table) {
            if ($driver === 'pgsql') {
                DB::statement(sprintf(
                    'DROP TABLE IF EXISTS "%s" CASCADE',
                    str_replace('"', '""', $table)
                ));
                continue;
            }

            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible cleanup migration.
    }

    private function dropColumnsIfExist(array $columnsByTable): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach ($columnsByTable as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($driver === 'pgsql') {
                    DB::statement(sprintf(
                        'ALTER TABLE "%s" DROP COLUMN IF EXISTS "%s" CASCADE',
                        str_replace('"', '""', $table),
                        str_replace('"', '""', $column)
                    ));
                    continue;
                }

                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->dropColumn($column);
                });
            }
        }
    }
};
