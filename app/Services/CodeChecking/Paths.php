<?php
namespace App\Services\CodeChecking;

class Paths
{
    public static function validate(mixed $path): string
    {
        abort_unless(is_string($path) && strlen($path) >= 1 && strlen($path) <= 240
            && !preg_match('/[\x00-\x1f\x7f\\\\:]/', $path) && !str_starts_with($path, '/'), 422, 'Invalid file path.');
        foreach (explode('/', $path) as $part) {
            abort_if(in_array($part, ['', '.', '..', '.git'], true), 422, 'Invalid file path.');
        }
        abort_if(in_array($path, ['program', '.executor'], true), 422, 'Reserved file path.');
        return $path;
    }
}
