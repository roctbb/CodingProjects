<?php

namespace App\Services;

use App\CourseActivity;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

class CoursePulse
{
    private const FRESH_SECONDS = 30;
    private const STALE_SECONDS = 120;
    private const LOCK_SECONDS = 15;

    public function forCourses($courseIds): array
    {
        $courseIds = collect($courseIds)->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)->unique()->sort()->values()->all();
        $key = $this->cacheKey($courseIds);
        $cached = Cache::get($key);

        if ($cached !== null) {
            if ($cached['generated_at'] + self::FRESH_SECONDS <= now()->getTimestamp()) {
                $this->deferRefresh($key, $courseIds, $cached['generated_at']);
            }
            return $cached['pulse'];
        }

        try {
            return Cache::lock($key.':lock', self::LOCK_SECONDS)->block(1, function () use ($key, $courseIds) {
                // Another request may have filled the cache while this one waited.
                $cached = Cache::get($key);
                return $cached !== null ? $cached['pulse'] : $this->refresh($key, $courseIds);
            });
        } catch (LockTimeoutException $e) {
            // A slow lock holder must not make the page fail. The uncached calculation
            // is inexpensive, and only the lock owner publishes a shared result.
            $cached = Cache::get($key);
            return $cached !== null ? $cached['pulse'] : CourseActivity::pulseForCourses($courseIds);
        }
    }

    private function cacheKey(array $courseIds): string
    {
        // Cache the aggregate for the authorized course set, shared by its users.
        return 'course-pulse:v1:'.hash('sha256', implode(',', $courseIds));
    }

    private function refresh(string $key, array $courseIds): array
    {
        $pulse = CourseActivity::pulseForCourses($courseIds);
        Cache::put($key, ['pulse' => $pulse, 'generated_at' => now()->getTimestamp()], self::STALE_SECONDS);
        return $pulse;
    }

    private function deferRefresh(string $key, array $courseIds, int $generation): void
    {
        defer(function () use ($key, $courseIds, $generation) {
            try {
                Cache::lock($key.':lock', self::LOCK_SECONDS)->get(function () use ($key, $courseIds, $generation) {
                    $cached = Cache::get($key);
                    if ($cached !== null && $cached['generated_at'] !== $generation) {
                        return;
                    }
                    $this->refresh($key, $courseIds);
                });
            } catch (\Throwable $e) {
                Log::warning('Could not refresh course pulse cache', ['exception' => $e]);
            }
        }, $key.':refresh');
    }
}
