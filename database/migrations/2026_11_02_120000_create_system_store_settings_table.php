<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_store_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->json('settings');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_store_settings');
    }
};
