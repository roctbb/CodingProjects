<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddModerationFieldsToThemesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->enum('moderation_status', ['pending', 'approved', 'banned'])->default('pending');
            $table->timestamp('moderated_at')->nullable();
            $table->unsignedInteger('moderated_by')->nullable();
            $table->foreign('moderated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['moderation_status', 'moderated_at', 'moderated_by']);
        });
    }
}
