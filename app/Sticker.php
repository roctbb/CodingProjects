<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Sticker extends Model
{
    protected $fillable = ['image', 'name', 'collection'];

    public static function catalog()
    {
        return static::query()->orderBy('collection')->orderBy('id')->get();
    }

    public static function imageRules(): array
    {
        return ['required', 'string', 'max:255', Rule::anyOf([
            ['url:http,https'], ['regex:~^/stickers/[1-9][0-9]*\.png$~'],
        ]), Rule::unique('stickers', 'image')];
    }
}
