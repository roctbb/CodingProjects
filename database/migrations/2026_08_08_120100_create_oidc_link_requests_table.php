<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOidcLinkRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('oidc_link_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->string('oidc_issuer');
            $table->string('oidc_subject');
            $table->string('name');
            $table->string('email');
            $table->string('role');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('oidc_link_requests');
    }
}
