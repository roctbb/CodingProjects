<?php
// Only the explicitly enabled, localhost CLI test server may use synthetic auth.
if (PHP_SAPI !== 'cli-server' || getenv('UI_QA_MODE') !== '1') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$directory = getenv('UI_QA_DIRECTORY');
if (!$directory || !is_file($directory.'/base.sqlite')) { http_response_code(503); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($root.'/public'.$path)) return false;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$variant = $_GET['scenario'] ?? $_COOKIE['ui_qa_scenario'] ?? 'base';
if (!in_array($variant, ['base', 'reading', 'notebook', 'catalog-desktop', 'catalog-phone', 'submissions', 'submissions-desktop', 'submissions-phone', 'statistics-desktop', 'statistics-phone', 'subgroups-desktop', 'subgroups-phone', 'subgroups-large-desktop', 'subgroups-large-phone'], true)) $variant = 'base';
$actor = $_GET['actor'] ?? $_COOKIE['ui_qa_actor'] ?? 'student';
if (isset($_GET['actor'])) setcookie('ui_qa_actor', $actor, ['path' => '/']);
if (isset($_GET['scenario'])) setcookie('ui_qa_scenario', $variant, ['path' => '/']);
config(['app.env' => 'testing', 'app.debug' => false, 'app.url' => getenv('UI_QA_URL'), 'app.enable_recaptcha' => false,
    'app.key' => getenv('UI_QA_KEY'), 'app.cipher' => 'AES-256-CBC',
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => $directory.'/'.$variant.'.sqlite',
    'filesystems.disks.code_originals'=>['driver'=>'local','root'=>$directory.'/code-originals','throw'=>true],
    'filesystems.disks.code_checking' => ['driver'=>'local', 'root'=>$directory.'/code-checking', 'throw'=>true],
    'services.geekexecutor_secret' => str_repeat('qa-execution-',4), 'services.geekexecutor_url'=>'http://127.0.0.1:9',
    'services.geekexecutor_socket_url'=>getenv('UI_QA_URL'),
    'code_checking.ai_detection_enabled'=>false,
    'cache.default' => 'array', 'session.driver' => 'file', 'session.files' => $directory.'/sessions',
    'session.cookie' => 'ui_qa_session', 'session.expire_on_close' => true, 'session.secure' => false,
    'session.domain' => null, 'session.path' => '/', 'queue.default' => 'sync',
    'branding.colors' => ['primary' => '#345bd1', 'accent' => '#ffa117'],
    'branding.dark_colors' => ['primary' => '#91aaff', 'accent' => '#ffb64a'],
    'ai.provider' => 'openai', 'ai.shared_models' => true,
    'ai.providers' => [
        'openai' => ['key' => null, 'base_url' => 'https://api.example.test/v1', 'endpoint' => 'https://api.example.test/v1/responses',
            'proxy' => null, 'image_api' => 'responses', 'images_url' => null],
    ],
    'ai.defaults' => ['text' => ['model' => 'qa-text', 'timeout' => 90], 'image' => ['model' => 'qa-image', 'timeout' => 180],
        'recheck'=>['model'=>'qa-strong','timeout'=>150]],
    'ai.commands' => [],
    'services.telegram' => ['bot_token'=>'123:qa-fake','bot_username'=>'class_test_bot','api_url'=>'https://telegram.example','timeout'=>10,'proxy'=>null,'webhook_secret'=>'qa-secret'],
    'logging.default' => 'single', 'logging.channels.single.path' => $directory.'/server-errors.log']);
$app->setLocale('ru');
mt_srand(4);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Http::fake();
Illuminate\Support\Facades\Queue::fake();
Illuminate\Support\Facades\Notification::fake();
Carbon\Carbon::setTestNow('2026-11-11 12:00:00 Europe/Moscow');
Carbon\CarbonImmutable::setTestNow('2026-11-11 12:00:00 Europe/Moscow');
if (($_GET['palette'] ?? '') === 'violet') config(['branding.colors' => ['primary' => '#705782', 'accent' => '#b97762']]);
Illuminate\Support\Facades\Auth::setUser(App\User::findOrFail(['teacher' => 2, 'admin' => 5][$actor] ?? 1));
if (in_array($path, ['/login', '/register', '/password/reset'], true)) Illuminate\Support\Facades\Auth::forgetGuards();
$response = $kernel->handle(Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
