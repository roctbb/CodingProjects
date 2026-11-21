<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('code_test_purchases', fn (Blueprint $table) => $table->boolean('from_tutor')->default(false));
    }

    public function down(): void
    {
        Schema::table('code_test_purchases', fn (Blueprint $table) => $table->dropColumn('from_tutor'));
    }
};
