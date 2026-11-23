<?php
namespace App\Services\CodeChecking;

use App\CodeDraft;
use App\CodeSubmission;
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

    public function startCorrection(Request $request, int $course, int $task): void
    {
        $drafts = CodeDraft::workspace($request, $course, $task);
        abort_unless($request->query('from_submission'), 422);
        $source = CodeSubmission::where('user_id', $request->user()->id)->where('course_id', $course)
            ->where('task_id', $task)->whereNull('legacy_id')->findOrFail($request->query('from_submission'));
        if ((clone $drafts)->exists()) return;
        $repository = $source->mode === 'repository';
        $mode = $source->mode;
        $files = $repository ? [] : array_values(array_filter($source->files ?? [], fn ($file) => ($file['origin'] ?? 'student') !== 'task'));
        $config = CodeTaskChecker::findOrFail($task)->config;
        // A submitted source file can be edited directly when the task permits code.
        if ($mode === 'files' && count($files) === 1 && in_array('code', $config['submission_modes'], true)
            && preg_match('/\.(py|cpp|cc|c|js|java|cs|go|rs|ts)$/i', $files[0]['name'])) $mode = 'code';
        $value = ['mode' => $mode, 'language' => $source->language, 'text' => $source->answer_text ?? '',
            'files' => $files, 'source_metadata' => $repository
                ? app(Repositories::class)->parse($source->source_metadata['url'], $source->source_metadata['commit'] ?? $source->source_metadata['ref'] ?? null) : null];
        $this->payloads->validate($value, $config, true);
        $drafts->firstOrCreate([], ['revision' => 1, 'value' => $value]);
    }

    public function get(Request $request, int $course, int $task): array
    {
        $draft = CodeDraft::workspace($request, $course, $task)->first();
        abort_if(!$draft && $request->query('from_submission') !== null, 404);
        $value = $draft?->value ?? $this->initial(CodeTaskChecker::findOrFail($task)->config, $task);
        $files = [];
        foreach ($value['files'] as $index => $file) {
            $item = ['path' => $file['name'], 'bytes' => $file['artifact']['bytes'], 'is_answer' => (bool) ($file['is_answer'] ?? false)];
            if ($draft) $item['download_url'] = route('code-drafts.file', ['course' => $course, 'task' => $task, 'file' => $index, 'from_submission' => $request->query('from_submission')]);
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
            $draft = CodeDraft::workspace($request, $course, $task)->lockForUpdate()->first();
            abort_unless((int) ($draft?->revision ?? 0) === (int) $data['revision'], 409, 'Draft has changed.');
            abort_if(!$draft && $request->query('from_submission') !== null, 404);
            CodeDraft::workspace($request, $course, $task)->updateOrCreate([],
                ['value' => $value, 'revision' => (int) $data['revision'] + 1]);
        });
    }
}
