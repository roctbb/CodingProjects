<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrganisationToCourse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('short_name')->nullable();
            $table->text('description')->nullable()->change();
            $table->string('site')->nullable()->change();
            $table->string('logo')->nullable()->change();
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->integer("provider_id")->nullable();
            $table->foreign('provider_id')->references('id')
                ->on('providers')->onDelete('set null');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->integer("provider_id")->nullable();
            $table->foreign('provider_id')->references('id')
                ->on('providers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
