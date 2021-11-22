<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    //
    public $fillable = ["name", "price", "image", "description", "user_id"];
    static function boot()
    {
        parent::boot();
        static::deleting(function($obj) {
            \Storage::disk('local')->deleteDirectory('themes/'.$obj->id);
        });
    }   
    static function make($user_id, $name, $description, $css, $js, $price, $image)
    {
        $theme = \App\Theme::create([
            'user_id' => $user_id,
            'name' => $name,
            'price' => $price,
            'image' => $image,
            'description' => $description
        ]);


        // Create the theme in the storage, not in database
        \Storage::disk('local')->put('themes/'.$theme->id.'/script.js', $js);
        \Storage::disk('local')->put('themes/'.$theme->id.'/style.css', $css);
        return $theme;
    }

    function css()
    {
        return \Storage::disk('local')->get('themes/'.$this->id.'/style.css');
    }

    function js()
    {
        return \Storage::disk('local')->get('themes/'.$this->id.'/script.js');
    }

    static function modify($id, $name, $description, $image, $price, $css, $js)
    {
        $theme = \App\Theme::find($id);
        $theme->name = $name;
        $theme->image = $image;
        $theme->price = $price;
        $theme->description = $description;
        $theme->save();
        
        \Storage::disk('local')->put('themes/'.$theme->id.'/script.js', $js);
        \Storage::disk('local')->put('themes/'.$theme->id.'/style.css', $css);
    }

    function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}
