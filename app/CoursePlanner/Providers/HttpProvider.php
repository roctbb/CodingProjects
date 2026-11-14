<?php

namespace App\CoursePlanner\Providers;

use App\Course;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class HttpProvider implements ContentProvider
{
    public function __construct(private string $key, private array $config)
    {
    }

    public function manifest(Course $course, User $actor): array
    {
        return Cache::remember($this->cacheKey($course, $actor, ['manifest']), 60, function () use ($course, $actor) {
            $manifest = $this->request($course, $actor, 'manifest');
            if (($manifest['protocol_version'] ?? null) !== '1' || ($manifest['provider_key'] ?? null) !== $this->key
                || !is_array($manifest['resource_types'] ?? null) || count($manifest['resource_types']) > 50
                || ($manifest['pagination']['mode'] ?? null) !== 'cursor' || !($manifest['search']['server_side'] ?? false)) {
                throw new ProviderException('invalid_provider_response', 'Провайдер не поддерживает контракт каталога v1.');
            }
            foreach ($manifest['resource_types'] as $type) {
                if (!is_string($type['key'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $type['key']) || !is_string($type['label'] ?? null)) {
                    throw new ProviderException('invalid_provider_response', 'Некорректное описание типов провайдера.');
                }
            }
            return $manifest;
        });
    }

    public function catalog(Course $course, User $actor, array $query): array
    {
        $manifest = $this->manifest($course, $actor);
        $this->checkType($manifest, $query['type'] ?? '');
        return Cache::remember($this->cacheKey($course, $actor, $query), 60, function () use ($course, $actor, $query) {
            $params = $query;
            if (!empty($query['parent_type'])) {
                $params['type'] = $query['parent_type'];
                $params['external_id'] = $query['parent_external_id'] ?? '';
            }
            $response = $this->request($course, $actor, !empty($query['parent_type']) ? 'resources/children' : 'resources', $params);
            if (!is_array($response['items'] ?? null) || count($response['items']) > (int) ($query['limit'] ?? 25)
                || !is_array($response['page'] ?? null) || !is_bool($response['page']['has_more'] ?? null)) {
                throw new ProviderException('invalid_provider_response', 'Провайдер вернул некорректную страницу.');
            }
            $response['items'] = array_map(fn ($item) => $this->normalize($item), $response['items']);
            if ($response['page']['has_more'] && (empty($response['items']) || !is_string($response['page']['next_cursor'] ?? null) || $response['page']['next_cursor'] === '' || strlen($response['page']['next_cursor']) > 4096)) {
                throw new ProviderException('invalid_provider_response', 'Провайдер не вернул курсор следующей страницы.');
            }
            if (!$response['page']['has_more']) {
                $response['page']['next_cursor'] = null;
            }
            return $response;
        });
    }

    public function resource(Course $course, User $actor, string $type, string $externalId): array
    {
        $manifest = $this->manifest($course, $actor);
        $this->checkType($manifest, $type);
        $card = $this->normalize($this->request($course, $actor, 'resources/detail', ['type' => $type, 'external_id' => $externalId]));
        if ($card['type'] !== $type || $card['external_id'] !== $externalId) {
            throw new ProviderException('invalid_provider_response', 'Идентификатор материала в ответе не совпадает.');
        }
        $card['capabilities'] = collect($manifest['resource_types'])->firstWhere('key', $type)['capabilities'] ?? [];
        return $card;
    }

    private function request(Course $course, User $actor, string $path, array $query = []): array
    {
        $secret = $this->config['secret'] ?? null;
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');
        if (!$secret || !filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new ProviderException('provider_not_configured', 'Подключение провайдера ещё не настроено.');
        }
        $token = JWT::encode(['iss' => 'codingprojects', 'aud' => $this->key, 'purpose' => 'course-planner',
            'sub' => (string) $actor->id, 'scope' => 'catalog:read content:read', 'iat' => time(), 'exp' => time() + 60], $secret, 'HS256');
        try {
            $response = Http::acceptJson()->withToken($token)->connectTimeout(1)->timeout(5)->withoutRedirecting()
                ->get($base.'/integrations/course-planner/v1/'.$path, $query + ['course_id' => (string) $course->id]);
        } catch (ConnectionException $e) {
            throw new ProviderException('provider_unavailable', 'Провайдер не ответил. Сохранённый план доступен.');
        }
        if (!$response->successful()) {
            $status = in_array($response->status(), [401, 403, 404, 409, 422, 429], true) ? $response->status() : 503;
            $code = [401 => 'integration_unauthorized', 403 => 'access_denied', 404 => 'resource_not_found', 409 => 'cursor_expired',
                422 => 'invalid_filter', 429 => 'rate_limited', 503 => 'provider_unavailable'][$status];
            throw new ProviderException($code, match ($status) {
                403 => 'Провайдер не разрешил доступ к материалу.', 404 => 'Материал не найден у провайдера.',
                409 => 'Обновите список с первой страницы.', 401 => 'Проверьте настройки подключения провайдера.',
                default => 'Не удалось загрузить материалы провайдера. Попробуйте ещё раз.',
            }, $status);
        }
        if (strlen($response->body()) > 262144 || !is_array($response->json())) {
            throw new ProviderException('invalid_provider_response', 'Ответ провайдера превышает допустимый размер или имеет неверный формат.');
        }
        return $response->json();
    }

    private function normalize(mixed $item): array
    {
        if (!is_array($item)) {
            throw new ProviderException('invalid_provider_response', 'Некорректная карточка материала.');
        }
        $validator = Validator::make($item, ['external_id' => 'required|string|max:190', 'type' => 'required|string|max:80|regex:/^[a-zA-Z0-9_-]+$/',
            'type_label' => 'required|string|max:100', 'title' => 'required|string|max:500', 'description' => 'nullable|string|max:2000',
            'open_url' => 'nullable|url:http,https|max:2000', 'source_revision' => 'nullable|string|max:190', 'public_metadata' => 'nullable|array']);
        if ($validator->fails() || strlen(json_encode($item['public_metadata'] ?? [])) > 32768) {
            throw new ProviderException('invalid_provider_response', 'Некорректные поля карточки материала.');
        }
        return $validator->validated() + ['provider_key' => $this->key, 'public_metadata' => []];
    }

    private function checkType(array $manifest, string $type): void
    {
        if ($type !== '' && !in_array($type, array_column($manifest['resource_types'], 'key'), true)) {
            throw new ProviderException('invalid_filter', 'Неизвестный тип материала.', 422);
        }
    }

    private function cacheKey(Course $course, User $actor, array $query): string
    {
        return 'planner.catalog.'.hash('sha256', json_encode([$this->key, $this->config['url'] ?? '',
            hash('sha256', (string) ($this->config['secret'] ?? '')), $actor->id, $actor->role, $course->id, $query]));
    }
}
