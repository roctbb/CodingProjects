<?php

namespace Tests\Unit;

use App\Services\MoscowWeatherService;
use Carbon\Carbon;
use ReflectionClass;
use Tests\TestCase;

class MoscowWeatherServiceTest extends TestCase
{
    public function testSeasonLayerKeyUsesMoscowSeason()
    {
        $service = new MoscowWeatherService();

        $this->assertSame('season_winter', $service->seasonLayerKey(Carbon::parse('2026-01-15', 'Europe/Moscow')));
        $this->assertSame('season_spring', $service->seasonLayerKey(Carbon::parse('2026-04-15', 'Europe/Moscow')));
        $this->assertSame('season_summer', $service->seasonLayerKey(Carbon::parse('2026-07-15', 'Europe/Moscow')));
        $this->assertSame('season_autumn', $service->seasonLayerKey(Carbon::parse('2026-10-15', 'Europe/Moscow')));
    }

    public function testWeatherCodesMapToAvatarLayers()
    {
        $service = new MoscowWeatherService();

        $this->assertSame('weather_storm', $this->mapWeather($service, ['weather_code' => 95]));
        $this->assertSame('weather_snow', $this->mapWeather($service, ['weather_code' => 71]));
        $this->assertSame('weather_snow', $this->mapWeather($service, ['snowfall' => 0.2]));
        $this->assertSame('weather_rain', $this->mapWeather($service, ['weather_code' => 61]));
        $this->assertSame('weather_rain', $this->mapWeather($service, ['precipitation' => 0.1]));
        $this->assertSame('weather_cloudy', $this->mapWeather($service, ['cloud_cover' => 45]));
        $this->assertSame('weather_clear', $this->mapWeather($service, ['weather_code' => 0, 'cloud_cover' => 10]));
    }

    public function testAvatarLayerKeyFallsBackToSeasonDuringTests()
    {
        Carbon::setTestNow(Carbon::parse('2026-12-20 12:00:00', 'Europe/Moscow'));

        try {
            $this->assertSame('season_winter_weather_clear', (new MoscowWeatherService())->avatarLayerKey());
        } finally {
            Carbon::setTestNow();
        }
    }

    private function mapWeather(MoscowWeatherService $service, array $current): string
    {
        $method = (new ReflectionClass($service))->getMethod('mapWeatherToLayer');
        $method->setAccessible(true);

        return $method->invoke($service, $current);
    }
}
