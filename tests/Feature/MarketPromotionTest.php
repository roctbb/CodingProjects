<?php

namespace Tests\Feature;

use App\CoinTransaction;
use App\MarketDeal;
use App\MarketGood;
use App\Services\SystemStoreSettings;
use App\SystemStoreSetting;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketPromotionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-12 12:00:00', 'Europe/Moscow'));
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, email_verified_at TEXT, image TEXT, gender TEXT, birthday TEXT, birthday_hidden INTEGER DEFAULT 0, learning_avatar_config TEXT, custom_title TEXT, custom_title_expires_at TEXT, avatar_frame TEXT, avatar_frame_config TEXT, avatar_frame_expires_at TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE market_goods (id INTEGER PRIMARY KEY, name TEXT, description TEXT, price INTEGER, number INTEGER, in_stock INTEGER, sale_type TEXT DEFAULT 'regular', auction_finished_at TEXT, image TEXT);
            CREATE TABLE market_deals (id INTEGER PRIMARY KEY, good_id INTEGER, user_id INTEGER, price INTEGER, source TEXT, shipped INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);
            CREATE TABLE market_bids (id INTEGER PRIMARY KEY, good_id INTEGER, user_id INTEGER, amount INTEGER, created_at TEXT, updated_at TEXT);
            CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT);
            INSERT INTO users (id,name,email,role,email_verified_at) VALUES (1,'Student','student@example.test','student','2026-01-01'),(2,'Administrator','admin@example.test','admin','2026-01-01');
            INSERT INTO coin_transactions (user_id,price,comment) VALUES (1,1000,'Initial balance');
            INSERT INTO market_goods (id,name,price,number,in_stock,sale_type) VALUES (1,'Наклейка',101,2,1,'regular'),(2,'Значок',80,2,1,'regular'),(3,'Аукцион',100,2,1,'auction'),(4,'Архив',100,2,0,'regular');
            SQL);
        (require database_path('migrations/2026_11_02_120000_create_system_store_settings_table.php'))->up();
        (require database_path('migrations/2026_11_14_220000_harden_business_processes.php'))->up();
        $this->actingAs(User::findOrFail(2));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function settings(): SystemStoreSettings
    {
        return app(SystemStoreSettings::class);
    }

    private function promotion(array $overrides = []): array
    {
        $data = $this->settings()->all();
        $data['promotion'] = array_replace($data['promotion'], ['enabled' => true, 'name' => 'Неделя открытий', 'description' => 'Новые вещи для новых проектов',
            'discount_percent' => 50, 'items' => ['good:1', 'digital:premium_keyboard', 'digital:room_character', 'digital:avatar_frame_custom']], $overrides);
        return $data;
    }

    public function test_mixed_promotion_changes_only_selected_prices_without_mutating_base_prices(): void
    {
        $data = $this->promotion();
        $this->put('/insider/settings/system-store', ['settings' => $data])->assertRedirect();
        $this->assertSame(101, (int) MarketGood::find(1)->price);
        $this->assertSame(51, MarketGood::find(1)->purchasePrice());
        $this->assertSame(80, MarketGood::find(2)->purchasePrice());
        $this->assertSame(23, User::learningAvatarItemCatalog()['premium_keyboard']['cost']);
        $this->assertSame(30, (new User())->customTitleCost());
        $this->assertSame(50, $this->settings()->roomCharacterPrice());
        $this->assertSame(50, (new User())->customAvatarFrameCost(false));
        $this->assertSame(75, (new User())->customAvatarFrameCost(true));
        $this->assertSame(45, $this->settings()->basePrice('premium_keyboard'));
        $this->view('market.partials.promotion-banner')->assertSee('Неделя открытий')->assertSee('Новые вещи для новых проектов')->assertSee('promotion=1');
    }

    public function test_period_uses_moscow_time_and_both_boundaries(): void
    {
        $this->settings()->save($this->promotion(['starts_at' => '2026-11-12T12:05', 'ends_at' => '2026-11-12T12:10']), 2);
        $this->assertSame(101, MarketGood::find(1)->purchasePrice());
        Carbon::setTestNow(Carbon::parse('2026-11-12T09:05:00Z'));
        $this->assertSame(51, MarketGood::find(1)->purchasePrice());
        Carbon::setTestNow(Carbon::parse('2026-11-12T09:10:00Z'));
        $this->assertSame(101, MarketGood::find(1)->purchasePrice());
        $this->view('market.partials.promotion-banner')->assertDontSee('Неделя открытий');
    }

    public function test_retired_pumpkin_flag_no_longer_enables_discount_or_banner(): void
    {
        SystemStoreSetting::create(['id' => 1, 'settings' => ['pumpkin_promo' => ['enabled' => true, 'ends_at' => null]], 'updated_by' => 2]);
        $this->assertFalse($this->settings()->promotionActive());
        $this->assertArrayNotHasKey('pumpkin_promo', $this->settings()->all());
        $this->assertSame(100, $this->settings()->roomCharacterPrice());
        $this->view('market.partials.promotion-banner')->assertDontSee('Тыквенный спас');
    }

    public function test_unknown_duplicate_auction_and_invalid_promotion_settings_are_rejected(): void
    {
        foreach ([['items' => ['digital:unknown']], ['items' => ['good:999']], ['items' => ['good:3']], ['items' => ['good:1', 'good:1']],
            ['items' => []], ['name' => ''], ['discount_percent' => 0], ['discount_percent' => 100], ['discount_percent' => 20.5],
            ['starts_at' => '2026-11-13T12:00', 'ends_at' => '2026-11-12T12:00']] as $invalid) {
            $this->putJson('/insider/settings/system-store', ['settings' => $this->promotion($invalid)])->assertUnprocessable();
        }
        $this->assertDatabaseCount('system_store_settings', 0);
    }

    public function test_target_search_is_paginated_and_admin_only(): void
    {
        foreach (range(10, 38) as $id) DB::table('market_goods')->insert(['id' => $id, 'name' => 'Набор '.str_pad((string) $id, 3, '0', STR_PAD_LEFT), 'price' => 10, 'number' => 1, 'in_stock' => 1]);
        $first = $this->getJson('/insider/settings/system-store/targets?'.http_build_query(['type' => 'goods', 'q' => 'Набор']))->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('has_more', true);
        $second = $this->getJson('/insider/settings/system-store/targets?'.http_build_query(['type' => 'goods', 'q' => 'Набор', 'page' => 2]))->assertOk()->assertJsonCount(4, 'items')->assertJsonPath('has_more', false);
        $this->assertEmpty(array_intersect(array_column($first->json('items'), 'id'), array_column($second->json('items'), 'id')));
        $this->getJson('/insider/settings/system-store/targets?'.http_build_query(['type' => 'goods', 'q' => 'набор']))->assertOk()->assertJsonCount(25, 'items');
        $this->getJson('/insider/settings/system-store/targets?'.http_build_query(['type' => 'digital', 'q' => 'персонажа']))->assertOk()->assertJsonPath('items.0.id', 'digital:room_character')->assertJsonCount(1, 'items');
        $this->actingAs(User::findOrFail(1))->getJson('/insider/settings/system-store/targets?type=digital')->assertForbidden();
    }

    public function test_physical_purchase_charges_discounted_snapshot_and_rejects_stale_quotes(): void
    {
        $this->settings()->save($this->promotion(), 2);
        $user = User::findOrFail(1);
        $deal = MarketGood::find(1)->buy($user, 51);
        $this->assertSame(51, (int) $deal->price);
        $this->assertSame(949, (int) $user->balance());
        $this->assertSame(1, (int) MarketGood::find(1)->number);
        $this->settings()->save($this->promotion(['enabled' => false]), 2);
        try { MarketGood::find(1)->buy($user, 51); $this->fail('Stale quote must not charge a new price'); }
        catch (\RuntimeException $error) { $this->assertSame('market_price_changed', $error->getMessage()); }
        $this->assertSame(949, (int) $user->balance());
        $this->assertSame(1, MarketDeal::count());
        $this->assertSame(51, (int) $deal->fresh()->price);
    }

    public function test_unavailable_or_unaffordable_purchase_has_no_effect(): void
    {
        $this->settings()->save($this->promotion(), 2);
        $user = User::findOrFail(1);
        DB::table('coin_transactions')->where('user_id', 1)->update(['price' => 50]);
        $this->assertNull(MarketGood::find(1)->buy($user));
        $this->assertNull(MarketGood::find(3)->buy($user));
        $this->assertNull(MarketGood::find(4)->buy($user));
        $this->assertSame(50, (int) $user->balance());
        $this->assertDatabaseCount('market_deals', 0);
        $this->assertSame(2, (int) MarketGood::find(1)->number);
    }

    public function test_cancellation_refunds_the_selected_order_after_promotion_changes(): void
    {
        $this->settings()->save($this->promotion(), 2);
        $user = User::findOrFail(1);
        $discounted = MarketGood::find(1)->buy($user);
        $this->settings()->save($this->promotion(['enabled' => false]), 2);
        $fullPrice = MarketGood::find(1)->buy($user);
        $this->assertSame(848, (int) $user->balance());
        $this->post('/insider/market/cancel/'.$discounted->id)->assertRedirect();
        $this->assertSame(899, (int) $user->balance());
        $this->assertNotNull($fullPrice->fresh());
        $this->assertSame(-101, (int) CoinTransaction::where('price', -101)->first()->price);
        $this->post('/insider/market/cancel/'.$discounted->id)->assertRedirect();
        $this->assertSame(899, (int) $user->balance());
    }

    public function test_cancellation_keeps_order_search_page_and_rejects_external_return_urls(): void
    {
        $returns = [
            '/insider/market?orders_q=Student&active_page=2#market-orders' => '/insider/market?orders_q=Student&active_page=2#market-orders',
            url('/insider/market/orders?shipped_page=2') => url('/insider/market/orders?shipped_page=2'),
            'https://other.example/insider/market' => '/insider/market#market-orders',
            '//other.example/insider/market' => '/insider/market#market-orders',
            '/insider/market-elsewhere' => '/insider/market#market-orders',
        ];
        foreach ($returns as $return => $expected) {
            DB::table('market_goods')->where('id', 1)->update(['number' => 1]);
            $deal = MarketGood::find(1)->buy(User::find(1));
            $this->post('/insider/market/cancel/'.$deal->id,['return_url' => $return])->assertRedirect($expected);
        }
        $this->assertSame(1000, (int) User::find(1)->balance());
    }

    public function test_digital_checkout_uses_the_same_quote_as_catalog_and_guards_expiration(): void
    {
        $this->settings()->save($this->promotion(), 2);
        $user = User::findOrFail(1);
        $this->actingAs($user)->post('/insider/market/digital/premium_keyboard/buy', ['expected_cost' => 23])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('alert-title', 'Цифровой товар куплен');
        $this->assertSame(977, (int) $user->balance());
        $this->assertTrue($user->fresh()->learningAvatarOwnsItem('premium_keyboard'));
        $this->assertSame(-23, (int) CoinTransaction::where('price', '<', 0)->first()->price);
        // A new user keeps the old discounted quote after the campaign is disabled.
        $this->settings()->save($this->promotion(['enabled' => false]), 2);
        $this->actingAs(User::findOrFail(2))->post('/insider/market/digital/premium_keyboard/buy', ['expected_cost' => 23])->assertRedirect();
        $this->assertFalse(User::find(2)->learningAvatarOwnsItem('premium_keyboard'));
        $this->assertSame(0, (int) User::find(2)->balance());
    }

    public function test_cosmetic_purchase_and_minimum_price_use_the_promotion(): void
    {
        $this->settings()->save($this->promotion(['discount_percent' => 50, 'items' => ['digital:custom_title', 'digital:avatar_frame_neon']]), 2);
        $user = User::findOrFail(1);
        $this->actingAs($user)->post('/insider/profile/1/custom-title', ['custom_title' => 'Исследователь', 'expected_cost' => 15])->assertRedirect();
        $this->assertSame(985, (int) $user->balance());
        $this->post('/insider/profile/1/avatar-frame', ['avatar_frame' => 'neon', 'expected_cost' => 15])->assertRedirect();
        $this->assertSame(970, (int) $user->balance());
        $this->assertSame('neon', $user->fresh()->activeAvatarFrame());
        $this->assertSame(1, $this->settings()->quote('digital:custom_title', 1)['price']);
        $this->assertSame(0, $this->settings()->quote('digital:custom_title', 1)['discount_percent']);
    }
}
