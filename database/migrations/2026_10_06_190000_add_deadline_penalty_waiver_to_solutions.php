<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->timestamp('deadline_penalty_waived_at')->nullable();
            $table->unsignedInteger('deadline_penalty_waived_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn(['deadline_penalty_waived_at', 'deadline_penalty_waived_by']);
        });
    }
};
