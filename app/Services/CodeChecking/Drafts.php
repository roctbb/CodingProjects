<?php
namespace App\Services\CodeChecking;

use App\CodeDraft;
use App\CodeTaskChecker;
use App\CodeTaskFile;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Drafts
{
    public function __construct(private SubmissionPayload $payloads, private Artifacts $artifacts) {}

    public function initial(array $config, int $task): array
    {
        $mode = $config['submission_modes'][0] ?? 'code';
        $files = CodeTaskFile::where('task_id', $task)->where('kind', 'starter')->where('read_only', false)->get()
            ->map(fn ($file) => ['name' => $file->path, 'artifact' => $file->artifact])->all();
        return ['mode' => $mode, 'language' => $config['language'], 'text' => '', 'files' => $files, 'source_metadata' => null];
    }

    public function get(Request $request, int $course, int $task): array
    {
        $draft = CodeDraft::where('user_id', $request->user()->id)->where('course_id', $course)->where('task_id', $task)->first();
        $value = $draft?->value ?? $this->initial(CodeTaskChecker::findOrFail($task)->config, $task);
        $files = [];
        foreach ($value['files'] as $index => $file) {
            $item = ['path' => $file['name'], 'bytes' => $file['artifact']['bytes'], 'is_answer' => (bool) ($file['is_answer'] ?? false)];
            if ($draft) $item['download_url'] = route('code-drafts.file', ['course' => $course, 'task' => $task, 'file' => $index]);
            if ($file['artifact']['bytes'] <= 2097152) {
                $source = $this->artifacts->get($file['artifact']['blob_id']);
                if (mb_check_encoding($source, 'UTF-8') && !str_contains($source, "\0")) $item['content'] = $source;
            }
            $files[] = $item;
        }
        return ['revision' => (int) ($draft?->revision ?? 0), 'mode' => $value['mode'], 'language' => $value['language'],
            'text' => $value['text'], 'files' => $files, 'repository' => $value['source_metadata']];
    }

    public function save(Request $request, int $course, int $task): void
    {
        $data = $this->payloads->body($request);
        abort_unless(is_int($data['revision'] ?? null) || ctype_digit((string) ($data['revision'] ?? '')), 422, 'revision is required.');
        $config = CodeTaskChecker::findOrFail($task)->config;
        $value = $request->isMethod('delete') ? $this->initial($config, $task) : $this->payloads->parse($request, $config, $course, $task, true);
        DB::transaction(function () use ($request, $course, $task, $data, $value) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $draft = CodeDraft::where('user_id', $request->user()->id)->where('course_id', $course)->where('task_id', $task)->lockForUpdate()->first();
            abort_unless((int) ($draft?->revision ?? 0) === (int) $data['revision'], 409, 'Draft has changed.');
            CodeDraft::updateOrCreate(['user_id' => $request->user()->id, 'course_id' => $course, 'task_id' => $task],
                ['value' => $value, 'revision' => (int) $data['revision'] + 1]);
        });
    }
}
