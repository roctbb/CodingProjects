<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('code_execution_runs', fn (Blueprint $table) => $table->json('criteria')->nullable());
    }

    public function down(): void
    {
        Schema::table('code_execution_runs', fn (Blueprint $table) => $table->dropColumn('criteria'));
    }
};
