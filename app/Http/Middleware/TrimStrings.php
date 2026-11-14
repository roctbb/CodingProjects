<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as BaseTrimmer;

class TrimStrings extends BaseTrimmer
{
    protected function transform($key, $value)
    {
        if (request()->is('insider/courses/*/tasks/*/checker/files', 'insider/courses/*/tasks/*/code-draft',
            'insider/courses/*/tasks/*/code-submissions') && preg_match('/(^|\.)(content|text|code)$/D', $key)) return $value;
        return parent::transform($key, $value);
    }

    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array
     */
    protected $except = [
        'password',
        'password_confirmation',
        'code',
    ];
}
