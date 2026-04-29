<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropArticlesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('article_votes');
        Schema::dropIfExists('article_comments');
        Schema::dropIfExists('articles_tags');
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('articles');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Removed module; intentionally not recreated.
    }
}
