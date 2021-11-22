<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CoreAutoincrementFixProd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //

        DB::statement('ALTER SEQUENCE public.core_edges_id_seq RESTART WITH 1337');
        DB::statement('ALTER SEQUENCE public.core_nodes_id_seq RESTART WITH 1337');
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
