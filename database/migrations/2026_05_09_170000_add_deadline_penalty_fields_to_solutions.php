<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDeadlinePenaltyFieldsToSolutions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->integer('raw_mark')->unsigned()->nullable()->after('mark');
            $table->integer('deadline_penalty_amount')->unsigned()->default(0)->after('raw_mark');
            $table->integer('deadline_penalty_days')->unsigned()->default(0)->after('deadline_penalty_amount');
            $table->dateTime('deadline_penalty_paid_at')->nullable()->after('deadline_penalty_days');
        });

        DB::table('solutions')
            ->whereNotNull('mark')
            ->update(['raw_mark' => DB::raw('mark')]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn([
                'raw_mark',
                'deadline_penalty_amount',
                'deadline_penalty_days',
                'deadline_penalty_paid_at',
            ]);
        });
    }
}
