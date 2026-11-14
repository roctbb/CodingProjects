<?php

namespace App\Support;

use Illuminate\Http\Request;

final class CommunityNavigation
{
    public static function profileQuery(string $search, string $role, int $page): array
    {
        return array_filter([
            'community_q' => $search,
            'community_role' => $role === 'all' ? '' : $role,
            'community_page' => max(1, $page),
        ], fn ($value) => $value !== '');
    }

    public static function returnUrl(Request $request, int $userId): string
    {
        $value = $request->query('community_q', '');
        $search = is_string($value) ? mb_substr(trim($value), 0, 120) : '';
        $value = $request->query('community_role', 'all');
        $role = in_array($value, ['student', 'teacher'], true) ? $value : 'all';
        $value = $request->query('community_page', 1);
        $page = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $query = array_filter(['q' => $search, 'role' => $role === 'all' ? '' : $role, 'page' => $page > 1 ? $page : ''], fn ($value) => $value !== '');
        $url = url('/insider/community');

        $hasContext = $request->hasAny(['community_q', 'community_role', 'community_page']);

        return $url.($query ? '?'.http_build_query($query) : '').($hasContext ? '#community-user-'.$userId : '');
    }
}
