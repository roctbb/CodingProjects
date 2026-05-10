<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecheckCommentToSolutionsTable extends Migration
{
    public function up()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->text('recheck_comment')->nullable()->after('recheck_requested');
        });
    }

    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn('recheck_comment');
        });
    }
}
