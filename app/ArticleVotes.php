<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleVotes extends Model
{
    //
    public $fillable = ["amount", "user_id", "article_id"];
}
