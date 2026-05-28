<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lesson_early_accesses')) {
            return;
        }

        Schema::table('lesson_early_accesses', function (Blueprint $table) {
            if (!Schema::hasColumn('lesson_early_accesses', 'source')) {
                $table->string('source')->default('purchase')->after('user_id');
            }

            if (!Schema::hasColumn('lesson_early_accesses', 'granted_by')) {
                $table->unsignedInteger('granted_by')->nullable()->after('source');
                $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('lesson_early_accesses')) {
            return;
        }

        Schema::table('lesson_early_accesses', function (Blueprint $table) {
            if (Schema::hasColumn('lesson_early_accesses', 'granted_by')) {
                $table->dropForeign(['granted_by']);
                $table->dropColumn('granted_by');
            }

            if (Schema::hasColumn('lesson_early_accesses', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
