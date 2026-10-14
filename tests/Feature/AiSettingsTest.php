<?php

namespace Tests\Feature;

use App\AiSetting;
use App\Services\Ai\AiSettings;
use App\Services\ChatGptService;
use App\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    private const URL = '/insider/settings/ai';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array', 'ai.provider' => 'gateway',
            'ai.providers.gateway' => ['key' => 'env-gateway-key', 'endpoint' => 'https://gateway.example/v1/responses', 'proxy' => null],
            'ai.providers.openai' => ['key' => 'env-openai-key', 'endpoint' => 'https://openai.example/v1/responses', 'proxy' => null],
        ]);
        foreach (array_keys(AiSettings::COMMANDS) as $command) {
            config(['ai.commands.' . $command . '.provider' => null]);
        }
        DB::purge('sqlite');
        DB::unprepared('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT)');
        (require database_path('migrations/2026_10_14_130000_create_ai_settings_table.php'))->up();
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
        $settings = app(AiSettings::class)->forForm()['settings'];
        $secrets = [];
        foreach (array_keys(AiSettings::PROVIDERS) as $provider) {
            foreach (['key', 'proxy'] as $field) {
                $secrets[$provider][$field] = ['action' => 'keep'];
            }
        }
        foreach ($settings['commands'] as &$command) {
            unset($command['type']);
        }
        unset($command);

        return ['settings' => $settings, 'secrets' => $secrets];
    }

    public function testAdminCanViewSettingsWithoutReceivingSecrets(): void
    {
        $this->login();
        config(['ai.providers.gateway.proxy' => 'socks5h://private-user:private-password@proxy.example:1080']);
        $response = $this->get(self::URL)->assertOk()->assertSee('Настройки ИИ')->assertSee('Исправление опечаток');
        $response->assertDontSee('env-gateway-key')->assertDontSee('env-openai-key')
            ->assertDontSee('private-password')->assertDontSee('private-user')->assertDontSee('proxy.example');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('href="' . route('settings.ai.edit') . '"', $response->getContent());
    }

    public static function deniedRoles(): array
    {
        return [['student'], ['teacher'], ['novice']];
    }

    #[DataProvider('deniedRoles')]
    public function testOnlyAdminCanReadWriteOrReset(string $role): void
    {
        $this->login($role);
        $this->get(self::URL)->assertForbidden();
        $this->put(self::URL, $this->payload())->assertForbidden();
        $this->delete(self::URL)->assertForbidden();
        $this->assertSame(0, AiSetting::count());
        $html = view('layouts.partials.sidebar-settings-link')->render();
        $this->assertStringNotContainsString('/settings/ai', $html);
    }

    public function testGuestCannotAccessSettings(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
        $this->put(self::URL, $this->payload())->assertRedirect(route('login'));
        $this->delete(self::URL)->assertRedirect(route('login'));
        $this->assertSame(0, AiSetting::count());
    }

    public function testSaveEncryptsSecretsAndExistingClientImmediatelyUsesNewRoutingAndProxy(): void
    {
        $this->login();
        $client = app(ChatGptService::class);
        $seen = [];
        Http::fake(function (Request $request, array $options) use (&$seen) {
            $seen[] = [$request, $options];
            $response = ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Готово']]]]];
            return Http::response($request->url() === 'https://gateway.example/v1/responses' ? ['result' => $response] : $response);
        });
        $client->generate([['role' => 'user', 'content' => 'before']], ['command' => 'text_improvement']);

        $payload = $this->payload();
        $payload['settings']['commands']['text_improvement'] = ['provider' => 'openai', 'model' => 'updated-model', 'timeout' => '45'];
        $payload['settings']['providers']['openai']['endpoint'] = 'https://custom.example/v1/responses';
        $payload['secrets']['openai']['key'] = ['action' => 'replace', 'value' => 'new-api-secret'];
        $payload['secrets']['openai']['proxy'] = ['action' => 'replace', 'value' => 'socks5h://user:proxy-secret@proxy.example:1080'];
        $this->put(self::URL, $payload)->assertRedirect(self::URL)->assertSessionHasNoErrors();

        $raw = DB::table('ai_settings')->value('settings');
        foreach (['new-api-secret', 'proxy-secret', 'updated-model', 'custom.example'] as $value) {
            $this->assertStringNotContainsString($value, $raw);
        }
        $this->assertSame(77, AiSetting::find(1)->updated_by);
        $this->assertArrayNotHasKey('settings', AiSetting::find(1)->toArray());
        $this->assertSame('Готово', $client->generate([['role' => 'user', 'content' => 'after']], ['command' => 'text_improvement']));
        $this->assertSame('https://gateway.example/v1/responses', $seen[0][0]->url());
        $this->assertSame('https://custom.example/v1/responses', $seen[1][0]->url());
        $this->assertTrue($seen[1][0]->hasHeader('Authorization', 'Bearer new-api-secret'));
        $this->assertSame('updated-model', $seen[1][0]['model']);
        $this->assertSame(45, $seen[1][1]['timeout']);
        $this->assertSame('socks5h://user:proxy-secret@proxy.example:1080', $seen[1][1]['proxy']);
        Http::assertSentCount(2);
        $this->get(self::URL)->assertOk()->assertDontSee('new-api-secret')->assertDontSee('proxy-secret');
    }

    public function testKeepClearAndInheritHaveDistinctMeanings(): void
    {
        $this->login();
        config(['ai.providers.openai.proxy' => 'http://env-proxy.example:8080']);
        $payload = $this->payload();
        $payload['secrets']['openai']['key'] = ['action' => 'replace', 'value' => 'saved-key'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $payload = $this->payload();
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertSame('saved-key', app(AiSettings::class)->all()['providers']['openai']['key']);

        $payload['secrets']['openai']['key'] = ['action' => 'clear'];
        $payload['secrets']['openai']['proxy'] = ['action' => 'clear'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertNull(app(AiSettings::class)->all()['providers']['openai']['key']);
        $this->assertNull(app(AiSettings::class)->all()['providers']['openai']['proxy']);

        $payload['secrets']['openai']['key'] = ['action' => 'inherit'];
        $payload['secrets']['openai']['proxy'] = ['action' => 'inherit'];
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertSame('env-openai-key', app(AiSettings::class)->all()['providers']['openai']['key']);
        $this->assertSame('http://env-proxy.example:8080', app(AiSettings::class)->all()['providers']['openai']['proxy']);
    }

    public function testValidationDoesNotSaveOrFlashSecrets(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['settings']['defaults']['text']['timeout'] = 900;
        $payload['secrets']['openai']['key'] = ['action' => 'replace', 'value' => 'do-not-flash-key'];
        $payload['secrets']['openai']['proxy'] = ['action' => 'replace', 'value' => 'socks5h://user:do-not-flash-pass@proxy.example:1080'];
        $response = $this->from(self::URL)->put(self::URL, $payload);
        $response->assertRedirect(self::URL)->assertSessionHasErrors('settings.defaults.text.timeout');
        $this->assertSame(0, AiSetting::count());
        $this->assertNull(session()->getOldInput('secrets'));
        $this->assertStringNotContainsString('do-not-flash', json_encode(session()->all()));
        $this->get(self::URL)->assertOk()->assertDontSee('do-not-flash');
    }

    public static function invalidSettings(): array
    {
        return [
            ['settings.provider', 'yandex'],
            ['settings.providers.openai.endpoint', 'file:///etc/passwd'],
            ['settings.providers.openai.endpoint', ['malformed']],
            ['settings.providers.openai.endpoint', 'https://user:password@example.com/v1/responses'],
            ['settings.commands.text_improvement.model', 'model with spaces'],
            ['settings.commands.achievement_image.timeout', 241],
            ['settings.commands.achievement_image.type', 'text'],
            ['settings.providers.openai.key', 'unvalidated-key'],
            ['secrets.openai.proxy.action', 'unknown'],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsAreRejectedAtomically(string $path, mixed $value): void
    {
        $this->login();
        $payload = $this->payload();
        data_set($payload, $path, $value);
        $this->put(self::URL, $payload)->assertSessionHasErrors();
        $this->assertSame(0, AiSetting::count());
        Http::assertNothingSent();
    }

    public static function invalidProxies(): array
    {
        return [['ftp://proxy.example:21'], ['https://proxy.example/path'], [['bad']], ['socks5h://'], ['http://proxy.example/?password=x']];
    }

    #[DataProvider('invalidProxies')]
    public function testInvalidProxyDoesNotEnterDatabaseOrSession(mixed $value): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['secrets']['gateway']['proxy'] = ['action' => 'replace', 'value' => $value];
        $this->put(self::URL, $payload)->assertSessionHasErrors('secrets.gateway.proxy.value');
        $this->assertSame(0, AiSetting::count());
        $this->assertNull(session()->getOldInput('secrets'));
    }

    public function testResetRestoresServerConfigurationWithoutRestartingClient(): void
    {
        $this->login();
        $settings = app(AiSettings::class);
        $payload = $this->payload();
        $payload['settings']['provider'] = 'openai';
        $this->put(self::URL, $payload)->assertSessionHasNoErrors();
        $this->assertSame('openai', $settings->all()['provider']);
        $this->delete(self::URL)->assertRedirect(self::URL);
        $this->assertSame('gateway', $settings->all()['provider']);
        $this->assertSame(0, AiSetting::count());
    }

    public function testBeforeMigrationAiContinuesUsingServerConfiguration(): void
    {
        Schema::drop('ai_settings');
        $this->assertSame(config('ai'), app(AiSettings::class)->all());
    }
}
