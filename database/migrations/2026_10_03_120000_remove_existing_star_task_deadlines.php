<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_deadlines')
            ->whereIn('task_id', DB::table('tasks')->select('id')->where('is_star', true))
            ->delete();
    }

    public function down(): void
    {
        // Removed deadlines cannot be reconstructed.
    }
};
