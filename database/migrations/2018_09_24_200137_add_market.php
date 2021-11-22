<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMarket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('market_goods', function (Blueprint $table) {

            $table->increments('id');
            $table->string('name');
            $table->text('description');
            $table->integer('price');
            $table->integer('number')->default(1);
            $table->boolean('in_stock')->default(true);
            $table->string("image")->nullable();


        });

        Schema::create('market_deals', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('good_id')->unsigned();
            $table->foreign('good_id')->references('id')
                ->on('market_goods')->onDelete('cascade');
            $table->timestamps();
            $table->boolean('shipped')->default('false');
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
