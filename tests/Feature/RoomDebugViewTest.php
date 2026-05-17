<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class RoomDebugViewTest extends TestCase
{
    use WithoutMiddleware;

    public function testRoomDebugRendersCharacterAsSlottedLayer()
    {
        $admin = new User(['name' => 'Admin', 'role' => 'admin']);
        $this->be($admin);

        $html = view('admin.room-debug', [
            'roomEditorSaveUrl' => '/insider/room-debug/layout',
            'roomSystem' => [
                'canvas' => ['width' => 1024, 'height' => 1024],
                'assetBasePath' => 'images/avatar-layers/room-system',
                'readiness' => [
                    'status' => 'ok',
                    'cards' => [[
                        'label' => 'Комнаты и слоты',
                        'value' => '17/17',
                        'status' => 'ok',
                        'hint' => '170/170 координат слотов готовы',
                    ], [
                        'label' => 'Сезоны и погода',
                        'value' => '20/20',
                        'status' => 'ok',
                        'hint' => '4 сезона × 5 погодных состояний',
                    ]],
                ],
                'rooms' => [[
                    'key' => 'room_01_home_start',
                    'name' => 'Домашний старт',
                    'rankFrom' => 'Рядовой',
                    'rankTo' => 'Ефрейтор',
                    'xpFrom' => 0,
                    'src' => '/images/avatar-layers/room-system/rooms/room_01_home_start.png',
                    'weatherPlacement' => ['scale' => 680, 'cropX' => 120, 'cropY' => 0],
                    'slots' => [[
                        'key' => 'character',
                        'label' => 'Персонаж',
                        'color' => '#ef4444',
                        'x' => 535,
                        'y' => 145,
                        'width' => 320,
                        'height' => 745,
                    ]],
                ]],
                'seasons' => [[
                    'key' => 'season_winter',
                    'name' => 'Зима',
                    'src' => '/images/avatar-layers/room-system/weather/season_winter.png',
                ]],
                'weatherStates' => [[
                    'key' => 'weather_clear',
                    'name' => 'Ясно',
                    'src' => '/images/avatar-layers/room-system/weather/weather_clear.png',
                ]],
                'defaultSeasonKey' => 'season_winter',
                'defaultWeatherStateKey' => 'weather_clear',
                'weather' => [[
                    'key' => 'season_winter_weather_clear',
                    'season' => 'season_winter',
                    'weather' => 'weather_clear',
                    'name' => 'Зима · Ясно',
                    'src' => '/images/avatar-layers/room-system/weather/season_winter_weather_clear.png',
                ]],
                'posters' => [[
                    'key' => 'default',
                    'name' => 'Базовый курс',
                    'src' => '/images/avatar-layers/room-system/posters/default.png',
                ]],
                'items' => [[
                    'key' => 'basic_laptop',
                    'name' => 'Ноутбук',
                    'src' => '/images/avatar-layers/room-system/items/basic_laptop.png',
                ]],
                'pets' => [[
                    'key' => 'learning_bot_pet',
                    'name' => 'Учебный бот',
                    'src' => '/images/avatar-layers/room-system/pets/learning_bot.png',
                ]],
                'safes' => [[
                    'key' => 'safe_empty',
                    'name' => 'Пустой сейф',
                    'src' => '/images/avatar-layers/room-system/safes/safe_empty.png',
                ]],
                'characters' => [
                    'boy' => [
                        'class_05' => [
                            'key' => 'class_05',
                            'label' => '5 класс',
                            'src' => '/images/avatar-layers/room-system/characters/boy/class_05.png',
                            'scale' => 0.82,
                        ],
                    ],
                    'girl' => [
                        'class_05' => [
                            'key' => 'class_05',
                            'label' => '5 класс',
                            'src' => '/images/avatar-layers/room-system/characters/girl/class_05.png',
                            'scale' => 0.82,
                        ],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString("scale: character.scale || 1", $html);
        $this->assertStringContainsString("img.style.transform = `scale(", $html);
        $this->assertStringContainsString('const slotOverlayOrder = {', $html);
        $this->assertStringContainsString('shelf_trophy_1: 50', $html);
        $this->assertStringContainsString('character: 60', $html);
        $this->assertStringContainsString('id="seasonSelect"', $html);
        $this->assertStringContainsString('weatherBySeasonAndState', $html);
        $this->assertStringContainsString('Картинка погоды', $html);
        $this->assertStringContainsString('weatherPlacementSlot(room)', $html);
        $this->assertStringContainsString('room-stage-card__badge--ok', $html);
        $this->assertStringContainsString('data-room-stage-card', $html);
        $this->assertStringContainsString('updateStageGridState', $html);
        $this->assertStringContainsString('Слои предпросмотра', $html);
        $this->assertStringContainsString('Режимы предпросмотра', $html);
        $this->assertStringContainsString('data-room-layer-preset="posterWeather"', $html);
        $this->assertStringNotContainsString('data-room-layer-preset="source"', $html);
        $this->assertStringNotContainsString('data-room-layer-toggle="source"', $html);
        $this->assertStringContainsString('data-room-layer-toggle="weather"', $html);
        $this->assertStringContainsString('const layerVisibility = {', $html);
        $this->assertStringContainsString('const layerPresets = {', $html);
        $this->assertStringContainsString('function setLayerVisibility', $html);
        $this->assertStringContainsString('if (layerVisibility.safe && safe)', $html);
        $this->assertStringContainsString('if (layerVisibility.character && character)', $html);
        $this->assertStringContainsString('id="roomCurrentInfo"', $html);
        $this->assertStringContainsString('function renderRoomInfo', $html);
        $this->assertStringNotContainsString('Alpha окна', $html);
        $this->assertStringNotContainsString('Alpha плаката', $html);
        $this->assertStringContainsString("document.addEventListener('keydown', onKeyboardNudge)", $html);
        $this->assertStringContainsString('function onKeyboardNudge', $html);
        $this->assertStringContainsString('function nudgeActiveSlot', $html);
        $this->assertStringContainsString('event.shiftKey ? 10 : 1', $html);
        $this->assertStringContainsString('event.altKey', $html);
        $this->assertStringContainsString('let layoutDirty = false', $html);
        $this->assertStringContainsString('let layoutSaveInFlight = false', $html);
        $this->assertStringContainsString('let dirtyRoomKeys = new Set()', $html);
        $this->assertStringContainsString('function setLayoutDirty', $html);
        $this->assertStringContainsString('function setLayoutSaving', $html);
        $this->assertStringContainsString('if (layoutSaveInFlight) return', $html);
        $this->assertStringContainsString('saveLayoutBtn.disabled = layoutSaveInFlight', $html);
        $this->assertStringContainsString('function markCurrentRoomDirty', $html);
        $this->assertStringContainsString('function markRoomClean', $html);
        $this->assertStringContainsString('function pruneDirtyRoomKeys', $html);
        $this->assertStringContainsString('dirtyRoomKeys.delete(roomKey)', $html);
        $this->assertStringContainsString('const dirtyRooms = rooms.filter', $html);
        $this->assertStringContainsString('const restoredRoomKeys = []', $html);
        $this->assertStringContainsString('Локальный черновик устарел', $html);
        $this->assertStringContainsString('room-action--dirty', $html);
        $this->assertStringContainsString("window.addEventListener('beforeunload'", $html);
        $this->assertStringContainsString('Есть несохраненные изменения layout.', $html);
        $this->assertStringContainsString('id="restoreDraftBtn"', $html);
        $this->assertStringContainsString('room-debug-layout-draft:', $html);
        $this->assertStringContainsString('function persistLayoutDraft', $html);
        $this->assertStringContainsString('function readLayoutDraft({dropStale = false} = {})', $html);
        $this->assertStringContainsString('const hasKnownRooms = draft.rooms.some', $html);
        $this->assertStringContainsString('readLayoutDraft({dropStale: true})', $html);
        $this->assertStringContainsString('function restoreLayoutDraft', $html);
        $this->assertStringContainsString('function clearLayoutDraft', $html);
        $this->assertStringContainsString('Локальный черновик layout восстановлен.', $html);
        $this->assertStringContainsString('if (layerVisibility.slots)', $html);
        $this->assertStringContainsString("object-fit: fill", $html);
        $this->assertStringContainsString("frame.className = 'room-layer room-weather-frame';", $html);
        $this->assertStringContainsString('id="weatherX" type="number" min="-2048" max="2048"', $html);
        $this->assertStringContainsString('id="weatherW" type="number" min="50" max="3072"', $html);
        $this->assertStringContainsString("withEmptyOption(data.items)", $html);
        $this->assertStringNotContainsString('Asset audit', $html);
        $this->assertStringNotContainsString('Прозрачность слотов', $html);
        $this->assertStringContainsString('Готовность системы комнат', $html);
        $this->assertStringContainsString('Комнаты и слоты', $html);
        $this->assertStringContainsString('Сезоны и погода', $html);
        $this->assertStringContainsString('room-readiness-card--ok', $html);
        $this->assertStringNotContainsString('Контроль прогрессии', $html);
        $this->assertStringNotContainsString('_room-progression-review.png', $html);
        $this->assertStringNotContainsString('Открытые сейфы', $html);
        $this->assertStringNotContainsString('_room-safe-open-review.png', $html);
        $this->assertStringContainsString('Библиотека слоев комнаты', $html);
        $this->assertStringContainsString('Питомцы', $html);
        $this->assertStringContainsString('Предметы стола', $html);
        $this->assertStringContainsString('window_weather', $html);
        $this->assertStringNotContainsString('_room-system-characters.png', $html);
        $this->assertStringNotContainsString('characters-sheet-v1.png', $html);
        $this->assertRoomDebugInlineScriptsHaveValidSyntax($html);
    }

    public function testRoomDebugLayoutSaveAllowsSmallWeatherScale()
    {
        $admin = new User(['name' => 'Admin', 'role' => 'admin']);
        $this->be($admin);

        $layoutPath = storage_path('framework/testing/room-debug-small-weather-scale/layouts.json');
        config(['learning_avatar.room_system.layouts_path' => $layoutPath]);

        if (!is_dir(dirname($layoutPath))) {
            mkdir(dirname($layoutPath), 0755, true);
        }

        file_put_contents($layoutPath, json_encode([
            'room_01_home_start' => [
                'window_weather' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'poster_wall' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'desk_center' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'safe_under_desk' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'character' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'shelf_trophy_1' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'shelf_trophy_2' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'shelf_trophy_3' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
                'pet_right' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100],
            ],
        ]));

        try {
            $this->postJson('/insider/room-debug/layout', [
                'room' => 'room_01_home_start',
                'slots' => [],
                'weather' => [
                    'scale' => 75,
                    'cropX' => 0,
                    'cropY' => 0,
                ],
            ])
                ->assertOk()
                ->assertJsonPath('weather.scale', 75);

            $layout = json_decode(file_get_contents($layoutPath), true);
            $this->assertSame(75, $layout['room_01_home_start']['_weather']['scale']);
        } finally {
            @unlink($layoutPath);
            @rmdir(dirname($layoutPath));
        }
    }

    public function testRoomDebugRouteRendersRoomSystemWithoutLegacyAiRoomPayload()
    {
        $this->be(new User([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->get('/insider/room-debug')
            ->assertOk()
            ->assertSee('Редактор комнаты ученика', false)
            ->assertSee('room-system', false)
            ->assertSee('room_01_home_start', false)
            ->assertSee('room_09_glass_office', false)
            ->assertSee('room_13_datacenter', false)
            ->assertSee('room_17_president_office', false)
            ->assertDontSee('room-debug/generated-v2-alpha', false);
    }

    public function testRoomDebugIsNotAvailableInProduction()
    {
        $originalEnvironment = app()->environment();
        $layoutPath = storage_path('framework/testing-room-layouts-production/layouts.json');
        @unlink($layoutPath);
        @rmdir(dirname($layoutPath));
        config(['learning_avatar.room_system.layouts_path' => $layoutPath]);

        $this->be(new User([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        try {
            $this->app->detectEnvironment(function () {
                return 'production';
            });

            $this->get('/insider/room-debug')->assertNotFound();
            $this->postJson('/insider/room-debug/layout', [
                'room' => 'room_01_home_start',
                'slots' => [
                    'character' => [
                        'x' => 10,
                        'y' => 20,
                        'width' => 300,
                        'height' => 700,
                    ],
                ],
            ])->assertNotFound();

            $this->assertFileDoesNotExist($layoutPath);
        } finally {
            $this->app->detectEnvironment(function () use ($originalEnvironment) {
                return $originalEnvironment;
            });
            @unlink($layoutPath);
            @rmdir(dirname($layoutPath));
        }
    }

    public function testRoomDebugLayoutSaveSanitizesPayloadIntoConfiguredLayoutFile()
    {
        $layoutPath = storage_path('framework/testing-room-layouts/testing-room-system-layouts.json');
        @unlink($layoutPath);
        @rmdir(dirname($layoutPath));
        mkdir(dirname($layoutPath), 0755, true);
        file_put_contents($layoutPath, json_encode([
            'room_01_home_start' => [
                'pet_right' => [
                    'x' => 700,
                    'y' => 760,
                    'width' => 180,
                    'height' => 160,
                ],
            ],
        ], JSON_PRETTY_PRINT) . PHP_EOL);
        config(['learning_avatar.room_system.layouts_path' => $layoutPath]);
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'Europe/Moscow'));

        try {
            $this->be(new User([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]));

            $response = $this->postJson('/insider/room-debug/layout', [
                'room' => 'room_01_home_start',
                'slots' => [
                    'character' => [
                        'x' => -20,
                        'y' => 900,
                        'width' => 9999,
                        'height' => 9999,
                    ],
                    'desk_center' => [
                        'x' => 120.4,
                        'y' => 300.6,
                        'width' => 240.2,
                        'height' => 80.8,
                    ],
                    'unknown_slot' => [
                        'x' => 1,
                        'y' => 1,
                        'width' => 1,
                        'height' => 1,
                    ],
                ],
                'weather' => [
                    'scale' => 9999,
                    'x' => -4096,
                    'y' => 4096,
                    'width' => 9999,
                    'height' => 25,
                    'cropX' => -50,
                    'cropY' => -4096,
                ],
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('slots.character.x', 0)
                ->assertJsonPath('slots.character.y', 900)
                ->assertJsonPath('slots.character.width', 1024)
                ->assertJsonPath('slots.character.height', 124)
                ->assertJsonPath('slots.desk_center.x', 120)
                ->assertJsonPath('slots.desk_center.y', 301)
                ->assertJsonPath('weather.x', -2048)
                ->assertJsonPath('weather.y', 2048)
                ->assertJsonPath('weather.width', 3072)
                ->assertJsonPath('weather.height', 50)
                ->assertJsonPath('weather.scale', 3072)
                ->assertJsonPath('weather.cropX', -50)
                ->assertJsonPath('weather.cropY', -2048)
                ->assertJsonPath('savedAt', '2026-05-15 12:00:00');

            $layout = json_decode(file_get_contents($layoutPath), true);

            $this->assertSame(0, $layout['room_01_home_start']['character']['x']);
            $this->assertSame(700, $layout['room_01_home_start']['pet_right']['x']);
            $this->assertArrayNotHasKey('unknown_slot', $layout['room_01_home_start']);
            $this->assertSame(-2048, $layout['room_01_home_start']['_weather']['x']);
            $this->assertSame(2048, $layout['room_01_home_start']['_weather']['y']);
            $this->assertSame(3072, $layout['room_01_home_start']['_weather']['width']);
            $this->assertSame(50, $layout['room_01_home_start']['_weather']['height']);
            $this->assertSame(3072, $layout['room_01_home_start']['_weather']['scale']);
            $this->assertSame(-50, $layout['room_01_home_start']['_weather']['cropX']);
            $this->assertSame(-2048, $layout['room_01_home_start']['_weather']['cropY']);
        } finally {
            Carbon::setTestNow();
            @unlink($layoutPath);
            @rmdir(dirname($layoutPath));
        }
    }

    public function testRoomDebugLayoutSaveCreatesMissingLayoutDirectory()
    {
        $layoutPath = storage_path('framework/testing-room-layouts-new/layouts.json');
        @unlink($layoutPath);
        @rmdir(dirname($layoutPath));
        config(['learning_avatar.room_system.layouts_path' => $layoutPath]);

        try {
            $this->be(new User([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]));

            $this->postJson('/insider/room-debug/layout', [
                'room' => 'room_01_home_start',
                'slots' => [
                    'character' => [
                        'x' => 10,
                        'y' => 20,
                        'width' => 300,
                        'height' => 700,
                    ],
                ],
            ])->assertOk();

            $this->assertFileExists($layoutPath);
            $this->assertSame(10, json_decode(file_get_contents($layoutPath), true)['room_01_home_start']['character']['x']);
        } finally {
            @unlink($layoutPath);
            @rmdir(dirname($layoutPath));
        }
    }

    public function testRoomDebugLayoutSaveRejectsInvalidPayloadWithoutWritingFile()
    {
        $layoutPath = storage_path('framework/testing-room-layouts-invalid/layouts.json');
        @unlink($layoutPath);
        @rmdir(dirname($layoutPath));
        config(['learning_avatar.room_system.layouts_path' => $layoutPath]);

        try {
            $this->be(new User([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]));

            $this->postJson('/insider/room-debug/layout', [
                'room' => 'missing_room',
                'slots' => [
                    'character' => [
                        'x' => 10,
                        'y' => 20,
                        'width' => 300,
                        'height' => 700,
                    ],
                ],
            ])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);

            $this->assertFileDoesNotExist($layoutPath);

            $this->postJson('/insider/room-debug/layout', [
                'room' => 'room_01_home_start',
                'slots' => 'not-an-array',
            ])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);

            $this->assertFileDoesNotExist($layoutPath);
        } finally {
            @unlink($layoutPath);
            @rmdir(dirname($layoutPath));
        }
    }

    private function assertRoomDebugInlineScriptsHaveValidSyntax($html)
    {
        preg_match_all('/<script>(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $index => $script) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'room-debug-js-');
            $this->assertIsString($temporaryPath);
            $this->assertNotFalse(file_put_contents($temporaryPath, $script));

            try {
                $output = [];
                exec('node --check ' . escapeshellarg($temporaryPath) . ' 2>&1', $output, $exitCode);
                $this->assertSame(0, $exitCode, 'Inline script #' . $index . ' has invalid JS syntax: ' . implode("\n", $output));
            } finally {
                @unlink($temporaryPath);
            }
        }
    }
}
