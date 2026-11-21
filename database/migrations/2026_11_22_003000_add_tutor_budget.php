<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tutor_budget_periods', function (Blueprint $table) {
            $table->date('month')->primary();
        });
        Schema::table('student_tutor_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('reserved_tokens')->default(0);
            $table->index('usage_day');
        });
    }

    public function down(): void
    {
        Schema::table('student_tutor_usage', function (Blueprint $table) {
            $table->dropIndex(['usage_day']);
            $table->dropColumn('reserved_tokens');
        });
        Schema::dropIfExists('tutor_budget_periods');
    }
};
