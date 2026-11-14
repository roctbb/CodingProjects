<?php

namespace App\Console\Commands;

use App\Course;
use App\CoursePlanner\Providers\HttpProvider;
use App\CoursePlanner\Providers\ProviderException;
use App\User;
use Illuminate\Console\Command;

final class CheckPlannerProviders extends Command
{
    protected $signature = 'planner:check-providers {--course= : Existing course ID} {--user= : Existing teacher or administrator ID} {--provider=* : Provider keys; defaults to all external providers}';
    protected $description = 'Read-only end-to-end check of provider manifests, search, pages and resource details';

    public function handle(): int
    {
        $course = Course::find($this->option('course'));
        $actor = User::find($this->option('user'));
        if (!$course || !$actor || !($actor->role === 'admin' || ($actor->role === 'teacher' && $course->teachers()->where('users.id', $actor->id)->exists()))) {
            $this->error('Укажите существующий курс и его преподавателя либо администратора.');
            return self::FAILURE;
        }
        $configs = collect(config('course_planner.providers'))->where('driver', 'http');
        $keys = $this->option('provider') ?: $configs->keys()->all();
        $failed = false;
        foreach ($keys as $key) {
            $config = $configs->get($key);
            if (!$config || empty($config['url']) || empty($config['secret'])) {
                $this->error($key.': подключение не настроено.');
                $failed = true;
                continue;
            }
            try {
                $driver = new HttpProvider($key, $config);
                $manifest = $driver->manifest($course, $actor);
                foreach ($manifest['resource_types'] as $type) {
                    $query = ['type' => $type['key'], 'query' => '', 'limit' => 2];
                    $page = $driver->catalog($course, $actor, $query);
                    foreach ($page['items'] as $item) {
                        $driver->resource($course, $actor, $item['type'], $item['external_id']);
                    }
                    if ($page['page']['has_more']) {
                        $next = $driver->catalog($course, $actor, $query + ['cursor' => $page['page']['next_cursor']]);
                        $firstIds = array_column($page['items'], 'external_id');
                        if (array_intersect($firstIds, array_column($next['items'], 'external_id'))) {
                            throw new ProviderException('repeated_page', 'Повтор материалов на следующей странице.');
                        }
                    }
                    if ($page['items']) {
                        $search = $driver->catalog($course, $actor, ['type' => $type['key'], 'query' => mb_substr($page['items'][0]['title'], 0, 80), 'limit' => 2]);
                        if (!$search['items']) {
                            throw new ProviderException('empty_search', 'Поиск по названию не вернул материал.');
                        }
                        if ($type['capabilities']['children'] ?? false) {
                            $driver->catalog($course, $actor, ['parent_type' => $type['key'], 'parent_external_id' => $page['items'][0]['external_id'], 'query' => '', 'limit' => 2]);
                        }
                    }
                    $this->info($key.' / '.$type['key'].': OK'.(!$page['items'] ? ' (каталог пуст; detail не проверен)' : ' (каталог, карточки, поиск'.($page['page']['has_more'] ? ', следующая страница' : '').')'));
                }
            } catch (ProviderException $e) {
                $this->error($key.': '.$e->errorCode.' — '.$e->getMessage());
                $failed = true;
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
