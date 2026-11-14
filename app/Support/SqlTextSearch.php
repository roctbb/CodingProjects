<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SqlTextSearch
{
    public static function pattern(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text)).'%';
    }

    // Column names are application constants; search text always remains a binding.
    public static function lower(string $column): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction('gc_search_lower', fn ($value) => mb_strtolower((string) $value), 1);
            return "gc_search_lower({$column})";
        }
        return "LOWER({$column})";
    }
}
