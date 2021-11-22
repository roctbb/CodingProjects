<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleComments extends Model
{
    //
    public $fillable = ["comment", "user_id", "article_id"];
    public function user()
    {
        return $this->belongsTo('\App\User');
    }

}
