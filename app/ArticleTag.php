<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleTag extends Model
{
    protected $table = 'article_tags';
    public function articles(){
        return $this->belongsToMany( 'App\Article', 'articles_tags', 'tag_id', 'article_id' );
    }
}
