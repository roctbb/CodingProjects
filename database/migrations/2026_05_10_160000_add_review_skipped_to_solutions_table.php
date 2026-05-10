<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewSkippedToSolutionsTable extends Migration
{
    public function up()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->boolean('review_skipped')->default(false)->after('recheck_requested');
        });
    }

    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn('review_skipped');
        });
    }
}
