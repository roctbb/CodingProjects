<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('planner_sessions') && !Schema::hasColumn('planner_sessions', 'comment')) {
            Schema::table('planner_sessions', fn (Blueprint $table) => $table->text('comment')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('planner_sessions', 'comment')) {
            Schema::table('planner_sessions', fn (Blueprint $table) => $table->dropColumn('comment'));
        }
    }
};
