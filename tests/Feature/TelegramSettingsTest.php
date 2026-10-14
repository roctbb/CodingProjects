<?php

namespace Tests\Feature;

use App\Services\TelegramBindService;
use App\Services\TelegramBot;
use App\Services\TelegramSettings;
use App\TelegramSetting;
use App\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    private const URL = '/insider/settings/telegram';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array', 'cache.default' => 'array',
            'services.telegram' => [
                'bot_token' => '123:env-secret', 'bot_username' => 'env_bot',
                'api_url' => 'https://telegram.example', 'timeout' => 10,
                'proxy' => 'socks5h://env-user:env-password@proxy.example:1080',
                'webhook_secret' => 'env-webhook-secret',
            ],
        ]);
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT)');
        (require database_path('migrations/2026_10_14_170000_create_telegram_settings_table.php'))->up();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
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

    private function payload(): array
    {
        $secrets = [];
        foreach (TelegramSettings::SECRETS as $field) {
            $secrets[$field] = ['action' => 'keep'];
        }

        return ['settings' => app(TelegramSettings::class)->forForm()['settings'], 'secrets' => $secrets];
    }

    public function testAdminCanViewWithoutSecretsAndFindSettingsInProfileMenu(): void
    {
        $this->login();
        $response = $this->get(self::URL)->assertOk()->assertSee('Настройки Telegram')->assertSee('Ключ бота');
        foreach (['env-secret', 'env-user', 'env-password', 'env-webhook-secret', 'proxy.example'] as $secret) {
            $response->assertDontSee($secret);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $settingsLink = '//a[contains(@href,"/settings/ai")]';
        $this->assertSame(0, $xpath->query('//ul[@class="gc-sidebar__nav"]' . $settingsLink)->length);
        $this->assertSame(2, $xpath->query('//ul[contains(@class,"dropdown-menu")]' . $settingsLink)->length);
    }

    public static function deniedRoles(): array
    {
        return [['student'], ['teacher'], ['novice']];
    }

    #[DataProvider('deniedRoles')]
    public function testOnlyAdminCanReadWriteAndReset(string $role): void
    {
        $this->login($role);
        $this->get(self::URL)->assertForbidden();
        $this->put(self::URL, $this->payload())->assertForbidden();
        $this->delete(self::URL)->assertForbidden();
        $this->assertSame(0, TelegramSetting::count());
    }

    public function testGuestsCannotAccessSettings(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
        $this->put(self::URL, $this->payload())->assertRedirect(route('login'));
        $this->delete(self::URL)->assertRedirect(route('login'));
    }

    public function testSaveEncryptsSecretsAndExistingClientUsesNewConnectionImmediately(): void
    {
        $this->login();
        $client = app(TelegramBot::class);
        $seen = [];
        Http::fake(function (Request $request, array $options) use (&$seen) {
            $seen[] = [$request, $options];
            return Http::response(['ok' => true, 'result' => []]);
        });
        $this->assertTrue($client->sendMessage(100, 'before'));
        $payload = $this->payload();
        $payload['settings'] = ['bot_username' => '@new_bot', 'api_url' => 'https://custom.example/prefix/', 'timeout' => '25'];
        $payload['secrets']['bot_token'] = ['action' => 'replace', 'value' => '456:new-secret'];
        $payload['secrets']['proxy'] = ['action' => 'replace', 'value' => 'socks5h://user:saved-proxy-password@new-proxy.example:1080'];
        $this->put(self::URL, $payload)->assertRedirect(self::URL)->assertSessionHasNoErrors();
        Http::assertSentCount(1); // Saving never sends a message or registers a webhook.

        $raw = DB::table('telegram_settings')->value('settings');
        foreach (['new-secret', 'saved-proxy-password', 'new_bot', 'custom.example'] as $value) {
            $this->assertStringNotContainsString($value, $raw);
        }
        $this->assertSame(77, TelegramSetting::find(1)->updated_by);
        $this->assertArrayNotHasKey('settings', TelegramSetting::find(1)->toArray());
        $this->assertSame('new_bot', app(TelegramSettings::class)->botUsername());
        $this->assertTrue($client->sendMessage(100, 'after', 'HTML'));
        $this->assertSame('https://custom.example/prefix/bot456:new-secret/sendMessage', $seen[1][0]->url());
        $this->assertSame('HTML', $seen[1][0]['parse_mode']);
        $this->assertSame(25, $seen[1][1]['timeout']);
        $this->assertFalse($seen[1][1]['allow_redirects']);
        $this->assertSame('socks5h://user:saved-proxy-password@new-proxy.example:1080', $seen[1][1]['proxy']);
        $client->getUpdates(9, 30);
        $this->assertSame(9, $seen[2][0]['offset']);
        $this->assertSame(35, $seen[2][1]['timeout']);
        $this->assertSame($seen[1][1]['proxy'], $seen[2][1]['proxy']);
        $this->get(self::URL)->assertOk()->assertDontSee('new-secret')->assertDontSee('saved-proxy-password');
    }

    public function testKeepClearInheritAndResetHaveDistinctMeanings(): void
    {
        $this->login();
        $settings = app(TelegramSettings::class);
        $payload = $this->payload();
        $payload['secrets']['bot_token'] = ['action' => 'replace', 'value' => '456:saved-secret'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $payload = $this->payload();
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertSame('456:saved-secret', $settings->all()['bot_token']);

        $payload['secrets']['proxy'] = ['action' => 'clear'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        Http::fake(function (Request $request, array $options) {
            $this->assertSame('', $options['proxy']);
            return Http::response(['ok' => true, 'result' => []]);
        });
        $this->assertTrue(app(TelegramBot::class)->sendMessage(100, 'direct'));
        $payload['secrets']['bot_token'] = ['action' => 'clear'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertFalse(app(TelegramBot::class)->sendMessage(100, 'disabled'));
        Http::assertSentCount(1);
        $this->assertNull($settings->all()['bot_token']);
        $payload['secrets']['bot_token'] = ['action' => 'inherit'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertSame('123:env-secret', $settings->all()['bot_token']);
        $this->delete(self::URL)->assertRedirect(self::URL);
        $this->assertSame(config('services.telegram'), $settings->all());
    }

    public static function invalidSettings(): array
    {
        return [
            ['settings.api_url', 'file:///etc/passwd'],
            ['settings.api_url', 'https://user:secret@example.com'],
            ['settings.api_url', 'https://example.com/bot123:secret'],
            ['settings.api_url', 'https://example.com?token=secret'],
            ['settings.api_url', ['malformed']],
            ['settings.timeout', 0],
            ['settings.bot_username', 'https://t.me/bot'],
            ['settings.bot_token', 'unvalidated-secret'],
            ['secrets.bot_token.action', 'unknown'],
            ['secrets.bot_token', ['action' => 'replace', 'value' => 'no-colon']],
            ['secrets.proxy', ['action' => 'replace', 'value' => 'ftp://proxy.example']],
            ['secrets.proxy', ['action' => 'replace', 'value' => 'socks5h://']],
            ['secrets.proxy', ['action' => 'replace', 'value' => ['malformed']]],
            ['secrets.webhook_secret', ['action' => 'replace', 'value' => 'with spaces']],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidInputDoesNotSaveOrFlashSecrets(string $path, mixed $value): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['secrets']['bot_token'] = ['action' => 'replace', 'value' => '456:do-not-flash'];
        data_set($payload, $path, $value);
        $this->from(self::URL)->put(self::URL, $payload)->assertSessionHasErrors();
        $this->assertSame(0, TelegramSetting::count());
        $this->assertNull(session()->getOldInput('secrets'));
        $this->assertStringNotContainsString('do-not-flash', json_encode(session()->all()));
        Http::assertNothingSent();
    }

    public function testWebhookUsesUpdatedSecret(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['secrets']['webhook_secret'] = ['action' => 'replace', 'value' => 'new-webhook-secret'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->mock(TelegramBindService::class)->shouldReceive('handleUpdate')->once()->with(['update_id' => 1])->andReturn(false);
        $this->postJson('/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'env-webhook-secret'])->assertForbidden();
        $this->postJson('/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'new-webhook-secret'])->assertOk();
        Http::assertNothingSent();
    }

    public function testProfileLinkUsesSavedBotUsername(): void
    {
        $this->login();
        DB::unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY, telegram_link_token TEXT, telegram_link_token_expires_at TEXT, updated_at TEXT)');
        DB::table('users')->insert(['id' => 77]);
        $payload = $this->payload();
        $payload['settings']['bot_username'] = '@updated_bot';
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $response = $this->get('/insider/profile/77/telegram-link');
        $token = DB::table('users')->where('id', 77)->value('telegram_link_token');
        $response->assertRedirect('https://t.me/updated_bot?start=bind_' . $token);
        $this->assertNotEmpty($token);
        Http::assertNothingSent();
    }

    public function testPollingDoesNotReuseOffsetsAcrossBotsAndKeepsCursorOnKeyRotation(): void
    {
        $this->login();
        Cache::put('telegram:poll:last_update_id', 500);
        $seen = [];
        Http::fake(function (Request $request) use (&$seen) {
            $seen[] = $request;
            return Http::response(['ok' => true, 'result' => [['update_id' => count($seen) === 1 ? 501 : 5]]]);
        });
        $this->artisan('telegram:poll --once')->assertSuccessful();
        $this->assertSame(501, $seen[0]['offset']);
        $payload = $this->payload();
        $payload['secrets']['bot_token'] = ['action' => 'replace', 'value' => '456:another-bot'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->artisan('telegram:poll --once')->assertSuccessful();
        $this->assertArrayNotHasKey('offset', $seen[1]->data());
        $payload['secrets']['bot_token']['value'] = '456:rotated-key';
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->artisan('telegram:poll --once')->assertSuccessful();
        $this->assertSame(6, $seen[2]['offset']);
    }

    public function testApiAndTransportFailuresDoNotLogSecretsOrReportSuccess(): void
    {
        $client = app(TelegramBot::class);
        $logs = [];
        Log::shouldReceive('warning')->twice()->andReturnUsing(function ($message, $context) use (&$logs) {
            $logs[] = [$message, $context];
        });
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls === 1) {
                return Http::response(['ok' => false, 'error_code' => 401, 'description' => 'env-secret env-password']);
            }
            throw new ConnectionException('https://telegram.example/bot123:env-secret via env-password');
        });
        $this->assertFalse($client->sendMessage(100, 'private message'));
        $this->assertSame([], $client->getUpdates());
        Http::assertSentCount(1); // Connection failures have no recorded response.
        $this->assertSame(2, $calls); // No retries or fallback to another bot.
        $this->assertStringNotContainsString('env-secret', json_encode($logs));
        $this->assertStringNotContainsString('env-password', json_encode($logs));
    }

    public function testBeforeMigrationServerConfigurationRemainsUsable(): void
    {
        Schema::drop('telegram_settings');
        $this->assertSame(config('services.telegram'), app(TelegramSettings::class)->all());
    }
}
