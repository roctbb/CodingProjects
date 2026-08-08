<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSilaederOidcIdentityToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oidc_issuer')->nullable();
            $table->string('oidc_subject')->nullable();
            $table->unique(['oidc_issuer', 'oidc_subject'], 'users_oidc_identity_unique');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_oidc_identity_unique');
            $table->dropColumn(['oidc_issuer', 'oidc_subject']);
        });
    }
}
