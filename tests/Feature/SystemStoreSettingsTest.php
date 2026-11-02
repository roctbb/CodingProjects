<?php

namespace Tests\Feature;

use App\Services\SystemStoreSettings;
use App\SystemStoreSetting;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemStoreSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT)');
        (require database_path('migrations/2026_11_02_120000_create_system_store_settings_table.php'))->up();
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00', 'Europe/Moscow'));
        $this->login();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(string $role = 'admin'): void
    {
        $user = new User();
        $user->forceFill(['id' => 77, 'name' => 'Administrator', 'role' => $role, 'email_verified_at' => now()]);
        $user->setRelation('courses', collect());
        $user->setRelation('managed_courses', collect());
        $this->actingAs($user);
    }

    public function test_admin_can_change_prices_used_by_store_and_paid_actions(): void
    {
        $this->get('/insider/settings/system-store')->assertOk()->assertSee('Цены системных товаров')->assertSee('Тыквенный спас 2026!');
        $data = app(SystemStoreSettings::class)->all();
        $data['prices'] = array_fill_keys(array_keys($data['prices']), 42);
        $this->put('/insider/settings/system-store', ['settings' => $data])->assertRedirect();
        $this->assertSame(77, SystemStoreSetting::find(1)->updated_by);
        $user = new User();
        $this->assertSame(42, $user->customTitleCost());
        $this->assertSame(42, $user->customAvatarFrameCost());
        $this->assertSame(42, User::avatarFrames()['neon']['cost']);
        $this->assertSame(42, User::learningAvatarItemCatalog()['learning_bot_pet']['cost']);
        $this->assertSame(0, User::learningAvatarItemCatalog()['basic_laptop']['cost']);
        $this->assertSame(42, (new \App\Lesson())->earlyAccessCost());
        $this->assertSame(42, (new \App\Solution())->xpBoosterCost());
        $this->assertSame(42, \App\RoomCharacterGeneration::currentCost());
    }

    public function test_only_admin_can_read_or_change_prices(): void
    {
        $data = app(SystemStoreSettings::class)->all();
        foreach (['student', 'teacher', 'novice'] as $role) {
            $this->login($role);
            $this->get('/insider/settings/system-store')->assertForbidden();
            $this->put('/insider/settings/system-store', ['settings' => $data])->assertForbidden();
        }
        $this->assertDatabaseCount('system_store_settings', 0);
    }

    public function test_invalid_and_unknown_prices_are_not_saved(): void
    {
        foreach ([-1, 0, 1.5, 100001, 'free'] as $price) {
            $data = app(SystemStoreSettings::class)->all();
            $data['prices']['room_character'] = $price;
            $this->putJson('/insider/settings/system-store', ['settings' => $data])->assertUnprocessable();
        }
        $data = app(SystemStoreSettings::class)->all();
        $data['prices']['unknown_item'] = 1;
        $this->putJson('/insider/settings/system-store', ['settings' => $data])->assertUnprocessable();
        $this->assertDatabaseCount('system_store_settings', 0);
    }

    public function test_promotion_changes_price_and_banner_and_expires_in_moscow_time(): void
    {
        $settings = app(SystemStoreSettings::class);
        $data = $settings->all();
        $data['pumpkin_promo'] = ['enabled' => true, 'ends_at' => '2026-11-08T23:59'];
        $this->put('/insider/settings/system-store', ['settings' => $data])->assertRedirect();
        $this->assertSame(50, $settings->roomCharacterPrice());
        $this->view('courses.partials.pumpkin_promo')->assertSee('Тыквенный спас 2026!')->assertSee('50 GC');
        Carbon::setTestNow(Carbon::parse('2026-11-08 23:59:01', 'Europe/Moscow'));
        $this->assertSame(100, $settings->roomCharacterPrice());
        $this->view('courses.partials.pumpkin_promo')->assertDontSee('Тыквенный спас 2026!');
    }
}
