<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            // Keep the accounting reference after the submission itself is removed.
            $table->unsignedInteger('solution_id')->nullable()->index();
            $table->unsignedInteger('reversal_of_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->dropIndex(['solution_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn(['solution_id', 'reversal_of_id']);
        });
    }
};
