<?php

namespace App\CoursePlanner\Providers;

use App\Course;
use App\CoursePlanner\Models\CourseProvider;
use App\CoursePlanner\Models\PlannerCourse;
use App\CoursePlanner\Models\Provider;
use App\CoursePlanner\Models\ResourceRef;
use App\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProviderRegistry
{
    public function listing(PlannerCourse $planner): array
    {
        $result = [];
        foreach (config('course_planner.providers', []) as $key => $config) {
            $provider = Provider::firstOrCreate(['key' => $key], ['display_name' => $config['name'], 'adapter_key' => $config['driver'], 'enabled' => true]);
            $courseProvider = CourseProvider::firstOrCreate(['planner_course_id' => $planner->id, 'provider_id' => $provider->id], ['enabled' => true, 'show_in_summary' => true]);
            $configured = $config['driver'] === 'native' || (filled($config['url'] ?? null) && filled($config['secret'] ?? null));
            $result[] = ['key' => $key, 'name' => $provider->display_name, 'configured' => $configured,
                'enabled' => $provider->enabled && $courseProvider->enabled, 'show_in_summary' => $courseProvider->show_in_summary];
        }
        return $result;
    }

    public function driver(string $key, PlannerCourse $planner): ContentProvider
    {
        $available = collect($this->listing($planner))->firstWhere('key', $key);
        if (!$available || !$available['enabled']) {
            throw new ProviderException('provider_not_found', 'Провайдер не подключён к курсу.', 404);
        }
        if (!$available['configured']) {
            throw new ProviderException('provider_not_configured', 'Подключение провайдера ещё не настроено.');
        }
        $config = config('course_planner.providers.'.$key);
        return $config['driver'] === 'native' ? app(NativeProvider::class) : new HttpProvider($key, $config);
    }

    public function resolve(PlannerCourse $planner, Course $course, User $actor, string $key, string $type, string $id): array
    {
        $card = $this->driver($key, $planner)->resource($course, $actor, $type, $id);
        $provider = Provider::where('key', $key)->firstOrFail();
        $ref = ResourceRef::updateOrCreate(['provider_id' => $provider->id, 'resource_type' => $card['type'], 'external_id' => $card['external_id']],
            ['title' => $card['title'], 'open_url' => $card['open_url'] ?? null, 'source_revision' => $card['source_revision'] ?? null,
                'public_metadata' => $card['public_metadata'] ?? [], 'last_checked_at' => now(), 'state' => 'available']);
        return [$ref, $card];
    }

    public function configure(PlannerCourse $planner, array $input): array
    {
        $data = Validator::make($input, ['providers' => 'required|array|min:1|max:100',
            'providers.*.key' => 'required|string|max:80|distinct', 'providers.*.enabled' => 'required|boolean',
            'providers.*.show_in_summary' => 'required|boolean'])->validate();
        $this->listing($planner);
        foreach ($data['providers'] as $item) {
            if (!array_key_exists($item['key'], config('course_planner.providers', []))) {
                throw ValidationException::withMessages(['providers' => 'Неизвестный провайдер.']);
            }
            $provider = Provider::where('key', $item['key'])->firstOrFail();
            CourseProvider::where('planner_course_id', $planner->id)->where('provider_id', $provider->id)
                ->update(['enabled' => $item['enabled'], 'show_in_summary' => $item['show_in_summary']]);
        }
        return [];
    }
}
