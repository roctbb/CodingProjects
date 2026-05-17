<?php

namespace App\Http\Controllers;

use App\Services\MoscowWeatherService;
use App\User;
use Illuminate\Http\Request;

class RoomDebugController extends Controller
{
    public function index()
    {
        $this->abortInProduction();

        $roomSystem = $this->roomSystemPayload();
        $roomEditorSaveUrl = url('/insider/room-debug/layout');

        return view('admin.room-debug', compact('roomSystem', 'roomEditorSaveUrl'));
    }

    public function saveLayout(Request $request)
    {
        $this->abortInProduction();

        $roomKey = (string) $request->input('room');
        $incomingSlots = $request->input('slots', []);
        $config = $this->roomSystemConfig();
        $knownRooms = collect($config['rooms'] ?? [])->pluck('key')->all();
        $allowedSlots = array_keys($this->slotLabels());

        if (!in_array($roomKey, $knownRooms, true) || !is_array($incomingSlots)) {
            return response()->json(['ok' => false, 'message' => 'Invalid room layout payload.'], 422);
        }

        $layoutsPath = $this->roomSystemLayoutsPath();
        $layouts = $this->jsonFile($layoutsPath, []);
        $sanitized = is_array($layouts[$roomKey] ?? null) ? $layouts[$roomKey] : [];
        foreach ($allowedSlots as $slotKey) {
            $slot = $incomingSlots[$slotKey] ?? null;
            if (!is_array($slot)) {
                continue;
            }

            $x = max(0, min(1024, (int) round((float) ($slot['x'] ?? 0))));
            $y = max(0, min(1024, (int) round((float) ($slot['y'] ?? 0))));
            $width = max(1, min(1024 - $x, (int) round((float) ($slot['width'] ?? 1))));
            $height = max(1, min(1024 - $y, (int) round((float) ($slot['height'] ?? 1))));
            $sanitized[$slotKey] = compact('x', 'y', 'width', 'height');
        }

        $incomingWeather = $request->input('weather', []);
        $legacyCropX = max(-2048, min(2048, (int) round((float) ($incomingWeather['cropX'] ?? 0))));
        $legacyCropY = max(-2048, min(2048, (int) round((float) ($incomingWeather['cropY'] ?? 0))));
        $windowSlot = $sanitized['window_weather'] ?? [];
        $weatherWidth = max(50, min(3072, (int) round((float) ($incomingWeather['width'] ?? ($incomingWeather['scale'] ?? 1024)))));
        $weatherHeight = max(50, min(3072, (int) round((float) ($incomingWeather['height'] ?? ($incomingWeather['scale'] ?? 1024)))));
        $weather = [
            'x' => max(-2048, min(2048, (int) round((float) ($incomingWeather['x'] ?? ((int) ($windowSlot['x'] ?? 0) - $legacyCropX))))),
            'y' => max(-2048, min(2048, (int) round((float) ($incomingWeather['y'] ?? ((int) ($windowSlot['y'] ?? 0) - $legacyCropY))))),
            'width' => $weatherWidth,
            'height' => $weatherHeight,
            'scale' => max($weatherWidth, $weatherHeight),
            'cropX' => $legacyCropX,
            'cropY' => $legacyCropY,
        ];

        $sanitized['_weather'] = $weather;
        $layouts[$roomKey] = $sanitized;
        $this->writeJsonFile($layoutsPath, $layouts);

        return response()->json([
            'ok' => true,
            'room' => $roomKey,
            'slots' => $sanitized,
            'weather' => $weather,
            'savedAt' => now()->toDateTimeString(),
        ]);
    }

    private function abortInProduction(): void
    {
        if (app()->environment('production')) {
            abort(404);
        }
    }

    private function slotStyle($slot, $canvas)
    {
        $canvasWidth = max(1, (int) ($canvas['width'] ?? 1024));
        $canvasHeight = max(1, (int) ($canvas['height'] ?? 1024));

        return 'left:' . round(100 * (int) ($slot['x'] ?? 0) / $canvasWidth, 4) . '%;'
            . 'top:' . round(100 * (int) ($slot['y'] ?? 0) / $canvasHeight, 4) . '%;'
            . 'width:' . round(100 * (int) ($slot['width'] ?? ($slot['w'] ?? 0)) / $canvasWidth, 4) . '%;'
            . 'height:' . round(100 * (int) ($slot['height'] ?? ($slot['h'] ?? 0)) / $canvasHeight, 4) . '%;'
            . '--slot-color:' . ($slot['color'] ?? '#38bdf8') . ';';
    }

    private function roomSystemPayload()
    {
        $config = $this->roomSystemConfig();
        $layouts = $this->roomSystemLayouts();
        $canvas = $config['canvas'] ?? ['width' => 1024, 'height' => 1024];
        $assetBasePath = trim($config['assetBasePath'] ?? 'images/avatar-layers/room-system', '/');
        $labels = $this->slotLabels();
        $colors = $this->slotColors();
        $rooms = collect($config['rooms'] ?? [])
            ->map(function ($room) use ($assetBasePath, $layouts, $canvas, $labels, $colors) {
                $roomKey = $room['key'];
                $roomSlots = $layouts[$roomKey] ?? [];
                $slots = collect($labels)
                    ->map(function ($label, $slotKey) use ($roomSlots, $canvas, $colors) {
                        $slot = $roomSlots[$slotKey] ?? ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1];

                        return [
                            'key' => $slotKey,
                            'label' => $label,
                            'color' => $colors[$slotKey] ?? '#38bdf8',
                            'x' => (int) ($slot['x'] ?? 0),
                            'y' => (int) ($slot['y'] ?? 0),
                            'width' => (int) ($slot['width'] ?? ($slot['w'] ?? 1)),
                            'height' => (int) ($slot['height'] ?? ($slot['h'] ?? 1)),
                            'style' => $this->slotStyle($slot + ['color' => $colors[$slotKey] ?? '#38bdf8'], $canvas),
                        ];
                    })
                    ->values()
                    ->all();

                return array_merge($room, [
                    'src' => $this->versionedAssetUrl($assetBasePath . '/' . ($room['file'] ?? '')),
                    'weatherPlacement' => $this->weatherPlacement($roomSlots['_weather'] ?? [], $roomSlots['window_weather'] ?? []),
                    'slots' => $slots,
                ]);
            })
            ->values()
            ->all();

        return [
            'canvas' => $canvas,
            'assetBasePath' => $assetBasePath,
            'readiness' => $this->roomSystemReadinessPayload($config, $layouts),
            'slotLabels' => $labels,
            'slotColors' => $colors,
            'hiddenEditorSlots' => ['window_weather'],
            'rooms' => $rooms,
            'seasons' => $this->assetsWithUrls($config['seasons'] ?? [], $assetBasePath),
            'weatherStates' => $this->assetsWithUrls($config['weatherStates'] ?? [], $assetBasePath),
            'defaultSeasonKey' => app(MoscowWeatherService::class)->seasonLayerKey(),
            'defaultWeatherStateKey' => app(MoscowWeatherService::class)->fallbackWeatherStateKey(),
            'weather' => $this->assetsWithUrls($config['weather'] ?? [], $assetBasePath),
            'posters' => $this->assetsWithUrls($config['posters'] ?? [], $assetBasePath),
            'items' => $this->assetsWithUrls($config['items'] ?? [], $assetBasePath),
            'pets' => $this->assetsWithUrls($config['pets'] ?? [], $assetBasePath),
            'safes' => $this->assetsWithUrls($config['safes'] ?? [], $assetBasePath),
            'characters' => $this->characterAssetsWithUrls($config['characters'] ?? [], $assetBasePath),
        ];
    }

    private function roomSystemReadinessPayload($config, $layouts)
    {
        $rooms = is_array($config['rooms'] ?? null) ? $config['rooms'] : [];
        $requiredSlotKeys = array_merge(array_keys($this->slotLabels()), ['_weather']);
        $expectedRoomCount = 17;
        $expectedSlotCount = count($rooms) * count($requiredSlotKeys);
        $completeRoomCount = 0;
        $readySlotCount = 0;

        foreach ($rooms as $room) {
            $roomKey = $room['key'] ?? null;
            $roomSlots = is_string($roomKey) && is_array($layouts[$roomKey] ?? null) ? $layouts[$roomKey] : [];
            $roomReady = true;

            foreach ($requiredSlotKeys as $slotKey) {
                $slot = $roomSlots[$slotKey] ?? null;
                $slotReady = is_array($slot)
                    && $this->hasNumericKeys($slot, ['x', 'y', 'width', 'height']);

                if ($slotReady) {
                    $readySlotCount++;
                } else {
                    $roomReady = false;
                }
            }

            if ($roomReady) {
                $completeRoomCount++;
            }
        }

        $seasons = is_array($config['seasons'] ?? null) ? $config['seasons'] : [];
        $weatherStates = is_array($config['weatherStates'] ?? null) ? $config['weatherStates'] : [];
        $expectedWeatherCount = count($seasons) * count($weatherStates);
        $readyWeatherKeys = collect($config['weather'] ?? [])
            ->filter(function ($weather) {
                return is_array($weather)
                    && !empty($weather['season'])
                    && !empty($weather['weather'])
                    && $this->roomSystemAssetExists($weather['file'] ?? null);
            })
            ->map(fn ($weather) => $weather['season'] . ':' . $weather['weather'])
            ->unique()
            ->count();

        $requiredClasses = ['class_05', 'class_06', 'class_07', 'class_08', 'class_09', 'class_10', 'class_11', 'student', 'teacher'];
        $requiredGenders = ['boy', 'girl'];
        $expectedCharacterCount = count($requiredClasses) * count($requiredGenders);
        $readyCharacterCount = 0;
        foreach ($requiredGenders as $gender) {
            foreach ($requiredClasses as $classKey) {
                $file = $config['characters'][$gender][$classKey] ?? null;
                if ($this->roomSystemAssetExists($file)) {
                    $readyCharacterCount++;
                }
            }
        }

        $cards = [
            [
                'label' => 'Комнаты и слоты',
                'value' => $completeRoomCount . '/' . count($rooms),
                'status' => $completeRoomCount === count($rooms) && count($rooms) === $expectedRoomCount ? 'ok' : 'warn',
                'hint' => $readySlotCount . '/' . $expectedSlotCount . ' координат слотов готовы',
            ],
            [
                'label' => 'Сезоны и погода',
                'value' => $readyWeatherKeys . '/' . $expectedWeatherCount,
                'status' => $expectedWeatherCount > 0 && $readyWeatherKeys === $expectedWeatherCount ? 'ok' : 'warn',
                'hint' => count($seasons) . ' сезона × ' . count($weatherStates) . ' погодных состояний',
            ],
            [
                'label' => 'Персонажи',
                'value' => $readyCharacterCount . '/' . $expectedCharacterCount,
                'status' => $readyCharacterCount === $expectedCharacterCount ? 'ok' : 'warn',
                'hint' => 'Мальчик и девочка, 5-11 класс + студент/преподаватель',
            ],
            [
                'label' => 'Каталог слоев',
                'value' => count($config['items'] ?? []) . '/' . count($config['pets'] ?? []),
                'status' => count($config['items'] ?? []) > 0 && count($config['pets'] ?? []) > 0 && count($config['safes'] ?? []) > 0 ? 'ok' : 'warn',
                'hint' => 'Предметы / питомцы, сейфы: ' . count($config['safes'] ?? []),
            ],
        ];

        return [
            'status' => collect($cards)->every(fn ($card) => ($card['status'] ?? null) === 'ok') ? 'ok' : 'warn',
            'cards' => $cards,
        ];
    }

    private function hasNumericKeys($payload, $keys)
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload) || !is_numeric($payload[$key])) {
                return false;
            }
        }

        return true;
    }

    private function roomSystemAssetExists($file)
    {
        return is_string($file)
            && $file !== ''
            && file_exists(public_path('images/avatar-layers/room-system/' . ltrim($file, '/')));
    }

    private function roomSystemConfig()
    {
        return $this->jsonFile(public_path('images/avatar-layers/room-system/config.json'), []);
    }

    private function roomSystemLayouts()
    {
        return $this->jsonFile($this->roomSystemLayoutsPath(), []);
    }

    private function roomSystemLayoutsPath()
    {
        return config('learning_avatar.room_system.layouts_path')
            ?: public_path('images/avatar-layers/room-system/layouts.json');
    }

    private function jsonFile($path, $default)
    {
        if (!file_exists($path)) {
            return $default;
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function writeJsonFile($path, $payload)
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporaryPath = tempnam($directory, basename($path) . '.tmp.');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Cannot create temporary room layout file.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false || !rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Cannot write room layout file.');
        }
    }

    private function assetsWithUrls($assets, $assetBasePath)
    {
        return collect($assets)
            ->map(function ($asset) use ($assetBasePath) {
                return array_merge($asset, [
                    'src' => $this->versionedAssetUrl($assetBasePath . '/' . ($asset['file'] ?? '')),
                ]);
            })
            ->values()
            ->all();
    }

    private function characterAssetsWithUrls($characters, $assetBasePath)
    {
        $payload = [];

        foreach ($characters as $gender => $classes) {
            foreach ($classes as $classKey => $file) {
                $grade = (int) str_replace('class_', '', $classKey);
                $label = str_replace('class_', '', $classKey) . ' класс';
                if ($classKey === 'student') {
                    $grade = 12;
                    $label = 'Выпускник';
                } elseif ($classKey === 'teacher') {
                    $grade = 12;
                    $label = 'Преподаватель';
                }

                $payload[$gender][$classKey] = [
                    'key' => $classKey,
                    'src' => $this->versionedAssetUrl($assetBasePath . '/' . $file),
                    'label' => $label,
                    'scale' => User::learningAvatarRoomSystemCharacterScale($grade),
                ];
            }
        }

        return $payload;
    }

    private function versionedAssetUrl($relativePath)
    {
        $relativePath = trim($relativePath, '/');
        $absolutePath = public_path($relativePath);

        return url($relativePath) . (file_exists($absolutePath) ? '?v=' . filemtime($absolutePath) : '');
    }

    private function weatherPlacement($placement, $windowSlot = [])
    {
        $placement = is_array($placement) ? $placement : [];
        $scale = max(50, min(3072, (int) ($placement['scale'] ?? ($placement['width'] ?? 1024))));
        $legacyCropX = max(-2048, min(2048, (int) ($placement['cropX'] ?? 0)));
        $legacyCropY = max(-2048, min(2048, (int) ($placement['cropY'] ?? 0)));

        return [
            'x' => max(-2048, min(2048, (int) ($placement['x'] ?? ((int) ($windowSlot['x'] ?? 0) - $legacyCropX)))),
            'y' => max(-2048, min(2048, (int) ($placement['y'] ?? ((int) ($windowSlot['y'] ?? 0) - $legacyCropY)))),
            'width' => max(50, min(3072, (int) ($placement['width'] ?? $scale))),
            'height' => max(50, min(3072, (int) ($placement['height'] ?? $scale))),
            'scale' => $scale,
            'cropX' => $legacyCropX,
            'cropY' => $legacyCropY,
        ];
    }

    private function slotLabels()
    {
        return [
            'window_weather' => 'Окно / погода',
            'poster_wall' => 'Плакат курса',
            'desk_center' => 'Предмет на столе',
            'safe_under_desk' => 'Сейф',
            'character' => 'Персонаж',
            'shelf_trophy_1' => 'Кубок 1',
            'shelf_trophy_2' => 'Кубок 2',
            'shelf_trophy_3' => 'Кубок 3',
            'pet_right' => 'Питомец',
        ];
    }

    private function slotColors()
    {
        return [
            'window_weather' => '#0ea5e9',
            'poster_wall' => '#2563eb',
            'desk_center' => '#f97316',
            'safe_under_desk' => '#64748b',
            'character' => '#ef4444',
            'shelf_trophy_1' => '#eab308',
            'shelf_trophy_2' => '#eab308',
            'shelf_trophy_3' => '#eab308',
            'pet_right' => '#a855f7',
        ];
    }
}
