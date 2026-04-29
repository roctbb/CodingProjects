<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('theme_settings');
        Schema::dropIfExists('theme_usings');
        Schema::dropIfExists('theme_boughts');
        Schema::dropIfExists('themes');
    }

    public function down(): void
    {
        // Theme customization was removed from the application.
    }
};
