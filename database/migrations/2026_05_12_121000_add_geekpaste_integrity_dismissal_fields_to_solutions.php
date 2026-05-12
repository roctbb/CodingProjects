<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGeekpasteIntegrityDismissalFieldsToSolutions extends Migration
{
    public function up()
    {
        Schema::table('solutions', function (Blueprint $table) {
            if (!Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_at')) {
                $table->dateTime('geekpaste_integrity_dismissed_at')->nullable()->after('geekpaste_integrity_synced_at');
            }

            if (!Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_by')) {
                $table->unsignedInteger('geekpaste_integrity_dismissed_by')->nullable()->after('geekpaste_integrity_dismissed_at');
            }
        });
    }

    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            if (Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_by')) {
                $table->dropColumn('geekpaste_integrity_dismissed_by');
            }

            if (Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_at')) {
                $table->dropColumn('geekpaste_integrity_dismissed_at');
            }
        });
    }
}
