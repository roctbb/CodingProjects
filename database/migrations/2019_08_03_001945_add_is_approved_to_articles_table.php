<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsApprovedToArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_draft')->default(true);

            $table->integer('reviewer_id')->unsigned()->nullable();
            $table->foreign('reviewer_id')->references('id')
                ->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('is_approved');
            $table->dropColumn('is_draft');
            $table->dropColumn('reviewer_id');
        });
    }
}
