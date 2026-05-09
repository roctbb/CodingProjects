<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAvatarFrameToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_frame')->nullable()->after('custom_title_expires_at');
            $table->timestamp('avatar_frame_expires_at')->nullable()->after('avatar_frame');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_frame', 'avatar_frame_expires_at']);
        });
    }
}
