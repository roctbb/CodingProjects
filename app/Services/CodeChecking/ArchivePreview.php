<?php

namespace App\Services\CodeChecking;

use App\CodeSubmission;

/** Reads the file list already saved by GeekPaste, never extracts the original ZIP. */
class ArchivePreview
{
    public const MAX_PREVIEW_BYTES = 2097152;
    private const MAX_SOURCE_JSON_BYTES = 16777216;
    private const MAX_METADATA_BYTES = 4194304;
    private array $loaded = [];

    public function __construct(private Artifacts $artifacts) {}

    public function available(CodeSubmission $submission, int $file): bool
    {
        $item = $submission->files[$file] ?? null;
        return $item && ($item['origin'] ?? 'student') !== 'task'
            && str_ends_with(strtolower($item['name'] ?? ''), '.zip')
            && isset($submission->source_metadata['raw_source_artifact']['blob_id'])
            && in_array($submission->source_metadata['legacy_metadata']['lang'] ?? null, ['zip', 'github'], true);
    }

    public function metadata(CodeSubmission $submission, int $file): array
    {
        $rows = $this->rows($submission, $file);
        $entries = [];
        $bytes = 0;
        // Do not apply native submission max_files or silently drop historical entries.
        foreach ($rows as $index => $row) {
            $entry = $this->describe($submission, $file, $index, $row);
            $bytes += strlen(json_encode($entry, JSON_THROW_ON_ERROR));
            abort_if($bytes > self::MAX_METADATA_BYTES, 422, 'Список файлов слишком большой для предпросмотра. Скачайте исходный архив.');
            $entries[] = $entry;
        }
        return ['origin' => 'saved_source', 'download_url' => $this->archiveUrl($submission, $file),
            'entries' => $entries, 'blocked_entries' => 0];
    }

    public function source(CodeSubmission $submission, int $file, string $id): array
    {
        [$entry, $content] = $this->selected($submission, $file, $id);
        abort_unless($entry['is_text'], 422, 'У этого файла нет сохранённого текста. Скачайте исходный архив.');
        abort_if($entry['bytes'] > self::MAX_PREVIEW_BYTES, 422, 'Файл слишком большой для предпросмотра. Скачайте его отдельно.');
        return array_intersect_key($entry, array_flip(['id', 'name', 'path', 'bytes', 'language'])) + ['source' => $content];
    }

    public function download(CodeSubmission $submission, int $file, string $id)
    {
        [$entry, $content] = $this->selected($submission, $file, $id);
        abort_unless($entry['is_text'], 422, 'Бинарный файл можно скачать в исходном архиве.');
        $name = basename(str_replace('\\', '/', $entry['name']));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '_', $name);
        if (in_array($name, ['', '.', '..'], true)) $name = 'saved-source.txt';
        return response()->streamDownload(fn () => print($content), $name, [
            'Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rows(CodeSubmission $submission, int $file): array
    {
        abort_unless($this->available($submission, $file), 404);
        $raw = $submission->source_metadata['raw_source_artifact'];
        abort_unless(is_int($raw['bytes'] ?? null) && $raw['bytes'] >= 0 && $raw['bytes'] <= self::MAX_SOURCE_JSON_BYTES
            && ($raw['sha256'] ?? null) === $raw['blob_id'], 422,
            'Сохранённый исходник недоступен для предпросмотра. Скачайте исходный архив.');
        $key = $raw['blob_id'];
        if (!array_key_exists($key, $this->loaded)) {
            $text = $this->artifacts->databaseContent($key);
            abort_unless($text !== null, 422, 'Сохранённая разбивка файлов недоступна. Исходный архив можно скачать.');
            abort_unless(strlen($text) === $raw['bytes'] && hash_equals($raw['sha256'], hash('sha256', $text)), 503, 'Не удалось прочитать сохранённый исходник. Повторите позже.');
            // Bound pathological JSON structures before allocating decoded arrays; never truncate a list.
            abort_if(substr_count($text, '{') + substr_count($text, '[') > 50000, 422, 'Сохранённая структура слишком сложна для предпросмотра. Скачайте исходный архив.');
            try { $value = json_decode($text, true, 64, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { abort(422, 'Сохранённая разбивка файлов недоступна. Исходный архив можно скачать.'); }
            $rows = is_array($value) && array_is_list($value) ? $value : ($value['files'] ?? null);
            abort_unless(is_array($rows) && array_is_list($rows), 422, 'В сохранённом исходнике нет списка файлов. Скачайте исходный архив.');
            $this->loaded[$key] = $rows;
        }
        return $this->loaded[$key];
    }

    private function describe(CodeSubmission $submission, int $file, int $index, mixed $row): array
    {
        $fields = is_array($row) ? $row : [];
        $name = is_string($fields['name'] ?? null) ? $fields['name'] : 'Файл '.($index + 1);
        $content = is_string($fields['content'] ?? null) ? $fields['content'] : null;
        $flag = $fields['is-binary'] ?? false;
        $binary = !in_array($flag, [false, 0, '0'], true)
            || ($content !== null && preg_match('/^Файл размером \d+ байт\.$/u', trim($content)) === 1);
        $text = !$binary && $content !== null && !str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8');
        $id = hash('sha256', $submission->source_metadata['raw_source_artifact']['blob_id']."\0".$file."\0".$index);
        $parameters = ['submission' => $submission->id, 'file' => $file, 'entry' => $id];
        return ['id' => $id, 'name' => $name, 'path' => $name, 'bytes' => $content !== null ? strlen($content) : null,
            'language' => $this->language($name), 'is_binary' => $binary, 'is_text' => $text,
            'previewable' => $text && strlen($content) <= self::MAX_PREVIEW_BYTES,
            'source_url' => route('code-submissions.archive-source', $parameters),
            'download_url' => $text ? route('code-submissions.archive-download', $parameters) : $this->archiveUrl($submission, $file),
            'download_kind' => $text ? 'file' : 'archive'];
    }

    private function selected(CodeSubmission $submission, int $file, string $id): array
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $id), 404);
        foreach ($this->rows($submission, $file) as $index => $row) {
            $expected = hash('sha256', $submission->source_metadata['raw_source_artifact']['blob_id']."\0".$file."\0".$index);
            if (hash_equals($expected, $id)) return [$this->describe($submission, $file, $index, $row), is_array($row) ? ($row['content'] ?? null) : null];
        }
        abort(404);
    }

    private function archiveUrl(CodeSubmission $submission, int $file): string
    {
        return route('code-submissions.file', ['submission' => $submission->id, 'file' => $file]);
    }

    private function language(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'py', 'pyw' => 'python', 'cpp', 'cc', 'cxx', 'h', 'hpp' => 'cpp', 'c' => 'c', 'cs' => 'csharp',
            'js', 'jsx' => 'javascript', 'ts', 'tsx' => 'typescript', 'java' => 'java', 'php' => 'php',
            'html', 'htm' => 'html', 'css' => 'css', 'json', 'ipynb' => 'json', 'xml', 'csproj', 'vcxproj' => 'xml',
            'yaml', 'yml' => 'yaml', 'sql' => 'sql', 'go' => 'go', 'rs' => 'rust', 'kt', 'kts' => 'kotlin',
            'md' => 'markdown', 'sh', 'bash' => 'bash', default => 'plaintext',
        };
    }
}
