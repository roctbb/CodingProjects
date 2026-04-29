<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropRemovedModulesTables extends Migration
{
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        $this->dropColumnIfExists('course_students', 'idea_id');
        $this->dropColumnIfExists('course_students', 'current_project_id');
        $this->dropColumnIfExists('courses', 'is_sdl');
        $this->dropColumnIfExists('courses', 'sdl_core_version');
        $this->dropColumnIfExists('lessons', 'is_sdl');
        $this->dropColumnIfExists('lessons', 'sdl_node_id');
        $this->dropColumnIfExists('lessons', 'scale_id');
        $this->dropColumnIfExists('program_chapters', 'scale_id');
        $this->dropColumnIfExists('educational_results', 'scale_id');
        $this->dropColumnIfExists('educational_results', 'result_id');
        $this->dropColumnIfExists('students_educational_results', 'scale_id');
        $this->dropColumnIfExists('students_educational_results', 'result_id');
        $this->dropColumnIfExists('tasks', 'result_id');
        $this->dropColumnIfExists('result_scales', 'core_node_id');
        $this->dropColumnIfExists('users', 'vk_id');

        foreach ([
            'feedback',
            'detailed_feedback',
            'sdl_courses_users_lessons',
            'core_consequences',
            'core_prerequisites',
            'core_edges',
            'core_nodes',
            'game_comments',
            'game_rewards',
            'game_votes',
            'games',
            'project_awards',
            'project_ideas',
            'project_students',
            'projects',
            'ideas',
            'forum_threads_subscribers',
            'forum_threads_tags',
            'forum_votes',
            'forum_comments',
            'forum_posts',
            'forum_tags',
            'forum_threads',
            'event_comments',
            'event_likes',
            'event_tags',
            'event_orgs',
            'event_partis',
            'events',
            'students_educational_results',
            'educational_results',
            'result_scales',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        // Removed modules are intentionally not restored.
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
            // Some legacy columns were created without Laravel's conventional FK name.
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropColumn($column);
        });
    }
}
