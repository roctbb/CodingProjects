<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MoscowWeatherService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.open-meteo.com',
            'connect_timeout' => 1,
            'timeout' => 2,
            'http_errors' => false,
        ]);
    }

    public function avatarLayerKey(): string
    {
        if (app()->runningUnitTests() || Carbon::hasTestNow() || !config('learning_avatar.weather.enabled', true)) {
            return $this->composeLayerKey($this->seasonLayerKey(), $this->fallbackWeatherStateKey());
        }

        $cacheMinutes = max(5, (int) config('learning_avatar.weather.cache_minutes', 30));

        return Cache::remember('learning-avatar:weather:moscow-layer', $cacheMinutes * 60, function () {
            return $this->fetchWeatherLayerKey()
                ?: $this->composeLayerKey($this->seasonLayerKey(), $this->fallbackWeatherStateKey());
        });
    }

    public function fallbackWeatherStateKey(): string
    {
        return 'weather_clear';
    }

    public function seasonLayerKey(?Carbon $date = null): string
    {
        $month = (int) ($date ?: Carbon::now(config('learning_avatar.weather.timezone', 'Europe/Moscow')))->format('n');

        if (in_array($month, [12, 1, 2], true)) {
            return 'season_winter';
        }

        if (in_array($month, [3, 4, 5], true)) {
            return 'season_spring';
        }

        if (in_array($month, [6, 7, 8], true)) {
            return 'season_summer';
        }

        return 'season_autumn';
    }

    protected function fetchWeatherLayerKey(): ?string
    {
        try {
            $response = $this->client->get('/v1/forecast', [
                'query' => [
                    'latitude' => config('learning_avatar.weather.latitude', 55.7558),
                    'longitude' => config('learning_avatar.weather.longitude', 37.6173),
                    'current' => 'weather_code,precipitation,rain,snowfall,cloud_cover,is_day',
                    'timezone' => config('learning_avatar.weather.timezone', 'Europe/Moscow'),
                    'forecast_days' => 1,
                ],
            ]);
        } catch (GuzzleException $exception) {
            Log::warning('Learning avatar weather request failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            Log::warning('Learning avatar weather request returned non-success status', [
                'status' => $response->getStatusCode(),
                'body' => mb_substr((string) $response->getBody(), 0, 500),
            ]);

            return null;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            return null;
        }

        return $this->composeLayerKey($this->seasonLayerKey(), $this->mapWeatherToLayer($payload['current'] ?? []));
    }

    protected function composeLayerKey(string $seasonKey, string $weatherStateKey): string
    {
        return $seasonKey . '_' . $weatherStateKey;
    }

    protected function mapWeatherToLayer($current): string
    {
        $code = (int) ($current['weather_code'] ?? 0);
        $precipitation = (float) ($current['precipitation'] ?? 0);
        $rain = (float) ($current['rain'] ?? 0);
        $snowfall = (float) ($current['snowfall'] ?? 0);
        $cloudCover = (int) ($current['cloud_cover'] ?? 0);

        if (in_array($code, [95, 96, 99], true)) {
            return 'weather_storm';
        }

        if ($snowfall > 0 || in_array($code, [71, 73, 75, 77, 85, 86], true)) {
            return 'weather_snow';
        }

        if ($precipitation > 0 || $rain > 0 || in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true)) {
            return 'weather_rain';
        }

        if ($cloudCover >= 45 || in_array($code, [1, 2, 3, 45, 48], true)) {
            return 'weather_cloudy';
        }

        return 'weather_clear';
    }
}
