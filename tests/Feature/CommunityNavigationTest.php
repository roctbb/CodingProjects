<?php

namespace Tests\Feature;

use App\Support\CommunityNavigation;
use Illuminate\Http\Request;
use Tests\TestCase;

class CommunityNavigationTest extends TestCase
{
    public function test_profile_round_trip_preserves_filters_page_and_selected_person(): void
    {
        $context = CommunityNavigation::profileQuery('Роботы & код', 'student', 3);
        $url = CommunityNavigation::returnUrl(Request::create('/insider/profile/666', 'GET', $context), 666);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('/insider/community', parse_url($url, PHP_URL_PATH));
        $this->assertSame(['q' => 'Роботы & код', 'role' => 'student', 'page' => '3'], $query);
        $this->assertSame('community-user-666', parse_url($url, PHP_URL_FRAGMENT));
    }

    public function test_first_page_returns_to_the_card_and_direct_profile_keeps_a_plain_back_link(): void
    {
        $context = CommunityNavigation::profileQuery('', 'all', 1);
        $url = CommunityNavigation::returnUrl(Request::create('/insider/profile/7', 'GET', $context), 7);
        $this->assertNull(parse_url($url, PHP_URL_QUERY));
        $this->assertSame('community-user-7', parse_url($url, PHP_URL_FRAGMENT));
        $this->assertSame(url('/insider/community'), CommunityNavigation::returnUrl(Request::create('/insider/profile/7'), 7));
    }

    public function test_malformed_context_cannot_select_arbitrary_destinations_or_query_parameters(): void
    {
        $request = Request::create('/insider/profile/7', 'GET', [
            'community_q' => ['invalid'], 'community_role' => 'admin', 'community_page' => [-1],
            'return' => 'https://other.example/', 'user_id' => 999,
        ]);
        $url = CommunityNavigation::returnUrl($request, 7);
        $this->assertSame(url('/insider/community').'#community-user-7', $url);

        $request = Request::create('/insider/profile/7', 'GET', ['community_q' => str_repeat('я', 200), 'community_page' => '-2']);
        parse_str(parse_url(CommunityNavigation::returnUrl($request, 7), PHP_URL_QUERY), $query);
        $this->assertSame(['q'], array_keys($query));
        $this->assertSame(120, mb_strlen($query['q']));
    }
}
