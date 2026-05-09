<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('market_goods', function (Blueprint $table) {
            $table->string('sale_type')->default('regular')->after('in_stock');
            $table->timestamp('auction_finished_at')->nullable()->after('sale_type');
        });

        Schema::table('market_deals', function (Blueprint $table) {
            $table->integer('price')->nullable()->after('good_id');
            $table->string('source')->default('purchase')->after('price');
        });

        Schema::create('market_bids', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('good_id')->unsigned();
            $table->foreign('good_id')->references('id')
                ->on('market_goods')->onDelete('cascade');

            $table->integer('amount');
            $table->timestamps();

            $table->unique(['good_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('market_bids');

        Schema::table('market_deals', function (Blueprint $table) {
            $table->dropColumn(['price', 'source']);
        });

        Schema::table('market_goods', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'auction_finished_at']);
        });
    }
};
