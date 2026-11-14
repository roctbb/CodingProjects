<?php
namespace App\Services\CodeChecking;

use App\CodeDraft;
use Illuminate\Http\Request;

class SubmissionPayload
{
    public function __construct(private Artifacts $artifacts) {}

    public function body(Request $request): array
    {
        try {
            $data = $request->isJson() ? json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR) : $request->all();
        } catch (\JsonException $exception) { abort(422, 'Invalid JSON.'); }
        abort_unless(is_array($data), 422, 'Expected an object.');
        return $data;
    }

    public function parse(Request $request, array $config, int $courseId, int $taskId, bool $draft = false): array
    {
        $input = $this->body($request);
        if (($input['use_draft'] ?? false) === true) {
            $saved = CodeDraft::where('user_id', $request->user()->id)->where('course_id', $courseId)->where('task_id', $taskId)->firstOrFail();
            abort_unless(($input['draft_revision'] ?? null) === (int) $saved->revision, 409, 'Draft has changed.');
            $value = $saved->value;
            $this->validate($value, $config, $draft);
            return $value;
        }
        $mode = $input['mode'] ?? (array_key_exists('code', $input) ? 'code' : (array_key_exists('text', $input) && !$request->hasFile('files') && !$request->hasFile('file') && empty($input['editor_files']) && empty($input['files']) ? 'text' : 'files'));
        $language = $input['language'] ?? $config['language'];
        $text = $input['text'] ?? '';
        abort_unless(is_string($text) && strlen($text) <= 2097152 && !str_contains($text, "\0"), 422, 'Invalid answer text.');
        $value = ['mode' => $mode, 'language' => $language, 'text' => $text, 'files' => [], 'source_metadata' => null];
        if ($mode === 'repository') {
            $value['source_metadata'] = app(Repositories::class)->parse($input['repository_url'] ?? '', $input['repository_ref'] ?? null);
        } else {
            if (isset($input['keep_files'])) {
                abort_unless(is_array($input['keep_files']) && count($input['keep_files']) <= 100, 422);
                $saved = CodeDraft::where('user_id', $request->user()->id)->where('course_id', $courseId)->where('task_id', $taskId)->firstOrFail();
                abort_unless((is_int($input['draft_revision'] ?? null) || ctype_digit((string) ($input['draft_revision'] ?? '')))
                    && (int) $input['draft_revision'] === (int) $saved->revision, 409, 'Draft has changed.');
                foreach ($input['keep_files'] as $index) {
                    abort_unless((is_int($index) || (is_string($index) && ctype_digit($index))) && isset($saved->value['files'][(int) $index]), 422);
                    $kept = $saved->value['files'][(int) $index];
                    if ($mode !== 'text' || empty($kept['is_answer'])) $value['files'][] = $kept;
                }
            }
            if (array_key_exists('code', $input)) {
                abort_unless(is_string($input['code']) && strlen($input['code']) <= 2097152, 422);
                $extension = ['python' => 'py', 'cpp' => 'cpp', 'text' => 'txt'][$language] ?? $language;
                $value['files'][] = ['name' => Paths::validate($input['filename'] ?? $config['entrypoint'] ?? 'solution.'.$extension),
                    'artifact' => $this->artifacts->put($input['code'])];
            }
            $editor = $input['editor_files'] ?? ($request->isJson() ? ($input['files'] ?? []) : []);
            abort_unless(is_array($editor) && count($editor) <= 100, 422);
            foreach ($editor as $file) {
                abort_unless(is_array($file) && is_string($file['content'] ?? null) && strlen($file['content']) <= 2097152, 422, 'Invalid editor file.');
                $value['files'][] = ['name' => Paths::validate($file['path'] ?? $file['name'] ?? null), 'artifact' => $this->artifacts->put($file['content'])];
            }
            $uploads = $request->file('files', []);
            if ($single = $request->file('file')) $uploads[] = $single;
            abort_unless(is_array($uploads) && count($uploads) <= 100, 422);
            $paths = $input['file_paths'] ?? [];
            abort_unless(is_array($paths), 422);
            foreach ($uploads as $index => $file) {
                abort_unless($file->isValid(), 422, 'Invalid uploaded file.');
                if (preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$file->getClientOriginalName())) {
                    $text=file_get_contents($file->getRealPath());
                    if(mb_check_encoding($text,'UTF-8'))$this->artifacts->put($text);
                }
                $value['files'][] = ['name' => Paths::validate($paths[$index] ?? basename(str_replace('\\', '/', $file->getClientOriginalName()))),
                    'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),
                    'artifact' => $this->artifacts->putFile($file->getRealPath())];
            }
            if ($mode === 'text' && !$value['files']) $value['files'][] = ['name' => 'answer.txt', 'is_answer' => true, 'artifact' => $this->artifacts->put($text)];
        }
        $this->validate($value, $config, $draft);
        return $value;
    }

    public function validate(array $value, array $config, bool $draft = false): void
    {
        abort_unless(in_array($value['mode'], $config['submission_modes'] ?? ['code', 'files'], true)
            && in_array($value['language'], $config['allowed_languages'] ?? [$config['language']], true), 422, 'Submission mode or language is not allowed.');
        abort_if(count($value['files']) > ($config['max_files'] ?? 20), 422, 'Too many files.');
        $names = []; $total = strlen($value['text']);
        foreach ($value['files'] as $file) {
            $name = Paths::validate($file['name']);
            abort_if(in_array($name, $names, true), 422, 'Duplicate file path.');
            foreach ($names as $existing) abort_if(str_starts_with($name.'/', $existing.'/') || str_starts_with($existing.'/', $name.'/'), 422, 'File path conflicts with a directory.');
            $names[] = $name;
            $total += $file['artifact']['bytes'];
            abort_if($file['artifact']['bytes'] > ($config['max_file_bytes'] ?? 52428800), 422, 'File exceeds task limit.');
            if (empty($file['is_answer']) && !empty($config['allowed_extensions'])) {
                abort_unless(in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $config['allowed_extensions'], true), 422, 'File extension is not allowed.');
            }
        }
        abort_if($total > ($config['max_total_bytes'] ?? 52428800), 422, 'Submission exceeds task limit.');
        abort_if($value['mode'] === 'code' && count($value['files']) !== 1, 422, 'Single editor requires one file.');
        if (!$draft) {
            abort_if($value['mode'] === 'code' && $value['files'][0]['artifact']['bytes'] === 0, 422, 'Source is empty.');
            abort_if($value['mode'] === 'text' && trim($value['text']) === '', 422, 'Answer is empty.');
            abort_if($value['mode'] !== 'repository' && !$value['files'], 422, 'Files are required.');
        }
    }
}
