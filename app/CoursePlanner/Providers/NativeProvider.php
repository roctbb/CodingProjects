<?php

namespace App\CoursePlanner\Providers;

use App\Course;
use App\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NativeProvider implements ContentProvider
{
    private const TYPES = ['lesson' => 'Урок', 'step' => 'Ступень', 'task' => 'Задача'];

    public function manifest(Course $course, User $actor): array
    {
        return ['protocol_version' => '1', 'provider_key' => 'codingprojects', 'display_name' => 'Программа CodingProjects',
            'resource_types' => collect(self::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label,
                'capabilities' => ['children' => $key !== 'task', 'course_deadline' => $key === 'task' ? 'source' : 'none']])->values()->all(),
            'pagination' => ['mode' => 'cursor', 'default_limit' => 25, 'max_limit' => 50], 'search' => ['server_side' => true]];
    }

    public function catalog(Course $course, User $actor, array $query): array
    {
        $type = $query['type'] ?? '';
        if ($type !== '' && !isset(self::TYPES[$type])) {
            throw new ProviderException('invalid_filter', 'Неизвестный тип материала.', 422);
        }
        $parentType = $query['parent_type'] ?? '';
        $parentId = $query['parent_external_id'] ?? '';
        if ($parentType && !in_array($parentType, ['lesson', 'step'], true)) {
            throw new ProviderException('invalid_filter', 'Неизвестный родительский материал.', 422);
        }
        $chapter = isset($query['chapter']) ? (int) $query['chapter'] : null;
        if ($chapter && !DB::table('program_chapters')->where('program_id', $course->program_id)->where('id', $chapter)->exists()) {
            throw new ProviderException('invalid_filter', 'Глава не найдена в программе курса.', 422);
        }
        $types = $type ? [$type] : ($parentType === 'lesson' ? ['step'] : ($parentType === 'step' ? ['task'] : array_keys(self::TYPES)));
        $signature = hash('sha256', json_encode([$course->id, $actor->id, $type, $parentType, $parentId, $query['query'] ?? '', $chapter]));
        $offset = 0;
        if (!empty($query['cursor'])) {
            $cursor = json_decode(base64_decode($query['cursor'], true) ?: '', true);
            if (!is_array($cursor) || ($cursor['signature'] ?? '') !== $signature || !is_int($cursor['offset'] ?? null) || $cursor['offset'] < 0 || $cursor['offset'] > 1000000) {
                throw new ProviderException('cursor_expired', 'Обновите каталог с первой страницы.', 409);
            }
            $offset = $cursor['offset'];
        }
        $limit = min(50, max(1, (int) ($query['limit'] ?? 25)));
        $union = null;
        foreach ($types as $kind) {
            $q = $this->query($course, $kind);
            $lesson = $kind === 'lesson' ? 'r' : 'l';
            if ($chapter !== null) {
                $chapter ? $q->where($lesson.'.chapter_id', $chapter) : $q->whereNull($lesson.'.chapter_id');
            }
            $q->leftJoin('program_chapters as c', 'c.id', '=', $lesson.'.chapter_id')
                ->selectRaw('COALESCE(c.sort_index, 2147483647) as chapter_order, COALESCE(c.id, 2147483647) as chapter_position')
                ->selectRaw('COALESCE('.$lesson.'.sort_index, 100) as lesson_order')
                ->selectRaw(($kind === 'lesson' ? '-2147483648' : 'COALESCE('.($kind === 'step' ? 'r' : 's').'.sort_index, 100)').' as step_order')
                ->selectRaw(($kind === 'lesson' ? '0' : ($kind === 'step' ? 'r.id' : 's.id')).' as step_position')
                ->selectRaw(($kind === 'task' ? 'COALESCE(r.sort_index, 100)' : '-2147483648').' as task_order');
            if ($parentType === 'lesson') {
                $q->where($kind === 'lesson' ? 'r.id' : 'l.id', $parentId);
            } elseif ($parentType === 'step') {
                $q->where($kind === 'task' ? 's.id' : 'r.id', $parentId);
            }
            if (filled($query['query'] ?? null)) {
                $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->whereRaw("r.name {$operator} ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query['query']).'%']);
            }
            $union ? $union->unionAll($q) : $union = $q;
        }
        $rows = DB::query()->fromSub($union, 'catalog');
        foreach (['chapter_order', 'chapter_position', 'lesson_order', 'lesson_id', 'step_order', 'step_position', 'task_order', 'external_id'] as $column) {
            $rows->orderBy($column);
        }
        $rows = $rows->offset($offset)->limit($limit + 1)->get(['external_id', 'name', 'source_updated_at', 'resource_type',
            'lesson_id', 'chapter_id', 'step_id', 'max_points', 'is_star', 'is_hidden']);
        $more = $rows->count() > $limit;
        return ['protocol_version' => '1', 'provider_key' => 'codingprojects',
            'items' => $rows->take($limit)->map(fn ($r) => $this->card($course, $r))->all(),
            'page' => ['has_more' => $more, 'next_cursor' => $more ? base64_encode(json_encode(['offset' => $offset + $limit, 'signature' => $signature])) : null, 'total_count' => null]];
    }

    public function resource(Course $course, User $actor, string $type, string $externalId): array
    {
        if (!isset(self::TYPES[$type]) || !preg_match('/^[1-9][0-9]{0,17}$/', $externalId)) {
            throw new ProviderException('resource_not_found', 'Материал не найден.', 404);
        }
        $row = $this->query($course, $type)->where('r.id', $externalId)->first();
        if (!$row) {
            throw new ProviderException('resource_not_found', 'Материал не найден в программе курса.', 404);
        }
        $card = $this->card($course, $row);
        if ($type === 'lesson') {
            $children = $this->query($course, 'step')->where('l.id', $externalId)->orderBy('r.sort_index')->orderBy('r.id')->limit(1001)->get();
            if ($children->count() > 1000) {
                throw new ProviderException('material_too_large', 'Добавляйте ступени этого урока по отдельности.', 422);
            }
            $card['children_refs'] = $children->map(fn ($r) => $this->card($course, $r))->all();
        }
        return $card;
    }

    private function query(Course $course, string $type): Builder
    {
        $q = match ($type) {
            'lesson' => DB::table('lessons as r')->where('r.program_id', $course->program_id),
            'step' => DB::table('program_steps as r')->join('lessons as l', 'l.id', '=', 'r.lesson_id')->where('l.program_id', $course->program_id),
            'task' => DB::table('tasks as r')->join('program_steps as s', 's.id', '=', 'r.step_id')->join('lessons as l', 'l.id', '=', 's.lesson_id')->where('l.program_id', $course->program_id),
        };
        return $q->select(['r.id as external_id', 'r.name', 'r.updated_at as source_updated_at'])
            ->selectRaw("'{$type}' as resource_type")
            ->selectRaw(($type === 'lesson' ? 'r.id' : 'l.id').' as lesson_id')
            ->selectRaw(($type === 'lesson' ? 'r.chapter_id' : 'l.chapter_id').' as chapter_id')
            ->selectRaw(($type === 'task' ? 's.id' : ($type === 'step' ? 'r.id' : 'CAST(NULL AS INTEGER)')).' as step_id')
            ->selectRaw(($type === 'task' ? 'r.max_mark' : 'CAST(NULL AS INTEGER)').' as max_points')
            ->selectRaw(($type === 'task' ? 'r.is_star' : 'FALSE').' as is_star')
            ->selectRaw(($type === 'task' ? 'r.is_hidden' : 'FALSE').' as is_hidden');
    }

    private function card(Course $course, object $row): array
    {
        $base = '/insider/courses/'.$course->id;
        $url = match ($row->resource_type) {
            'lesson' => $base.'?chapter='.$row->chapter_id,
            'step' => $base.'/steps/'.$row->external_id,
            'task' => $base.'/steps/'.$row->step_id.'#task'.$row->external_id,
        };
        return ['provider_key' => 'codingprojects', 'external_id' => (string) $row->external_id, 'type' => $row->resource_type,
            'type_label' => self::TYPES[$row->resource_type], 'title' => $row->name, 'open_url' => url($url),
            'source_revision' => hash('sha256', json_encode((array) $row)),
            'capabilities' => ['children' => $row->resource_type !== 'task', 'course_deadline' => $row->resource_type === 'task' ? 'source' : 'none'],
            'public_metadata' => ['lesson_id' => (int) $row->lesson_id, 'chapter_id' => (int) $row->chapter_id,
                'step_id' => $row->step_id ? (int) $row->step_id : null, 'is_star' => (bool) $row->is_star, 'is_hidden' => (bool) $row->is_hidden,
                'points' => $row->max_points === null ? null : ['value' => (int) $row->max_points, 'unit' => 'codingprojects.xp']]];
    }
}
