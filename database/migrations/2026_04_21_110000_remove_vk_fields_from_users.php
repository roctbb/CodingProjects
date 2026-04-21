<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        foreach (['vk', 'vk_id'] as $column) {
            if (!Schema::hasColumn('users', $column)) {
                continue;
            }

            if ($driver === 'pgsql') {
                DB::statement(sprintf(
                    'ALTER TABLE "users" DROP COLUMN IF EXISTS "%s" CASCADE',
                    str_replace('"', '""', $column)
                ));
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'vk')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('vk')->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'vk_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->bigInteger('vk_id')->nullable();
            });
        }
    }
};
