<?php

namespace App\Services\CodeChecking;

use App\CodeTaskChecker;
use App\Task;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Configuration
{
    public const LANGUAGES = ['text', 'cpp', 'python', 'java', 'cs', 'html', 'css', 'js', 'json', 'xml', 'swift', 'php', 'sql', 'brainfuck'];
    public const MODES = ['text', 'files', 'code', 'project', 'repository'];
    public const COMPARISONS = ['tokens', 'exact', 'float', 'tokens_ignore_cr', 'exact_crlf', 'float_binary64',
        'strip_suffix', 'lower_suffix', 'lower_exact', 'normalized_suffix', 'exact_suffix', 'last_time',
        'last_integer', 'integer_tokens', 'integer_tokens_ascii30', 'keyword_integers_ascii30',
        'word_integer_ascii30', 'splitlines', 'word_lower', 'rational_absolute', 'magic_mismatch'];

    public function __construct(private Artifacts $artifacts) {}

    public function save(Task $task, array $input, int $actor): CodeTaskChecker
    {
        abort_unless($task->max_mark > 0, 422, 'Checking requires a positive maximum grade.');
        $data = Validator::make($input, [
            'language' => ['required', Rule::in(array_merge(self::LANGUAGES, ['ipynb', 'zip', 'image', 'xlsx', 'github']))],
            'check_type' => ['required', Rule::in(['manual', 'tests', 'gpt'])],
            'test_mode' => ['sometimes', Rule::in(['io', 'environment', 'suite'])],
            'private_instructions' => 'sometimes|nullable|string|max:200000',
            'submission_modes' => 'sometimes|array|min:1|max:5',
            'submission_modes.*' => ['required', 'distinct', Rule::in(self::MODES)],
            'allowed_languages' => 'sometimes|array|min:1|max:20',
            'allowed_languages.*' => ['required', 'distinct', Rule::in(self::LANGUAGES)],
            'allowed_extensions' => 'sometimes|array|max:50',
            'allowed_extensions.*' => 'string|regex:/^[a-zA-Z0-9]{1,12}$/D|distinct',
            'max_files' => 'sometimes|integer|min:1|max:100',
            'max_file_bytes' => 'sometimes|integer|min:1|max:52428800',
            'max_total_bytes' => 'sometimes|integer|min:1|max:52428800',
            'entrypoint' => 'sometimes|nullable|string|max:240',
            'reference_answer' => 'sometimes|nullable|string|max:200000',
            'gpt_model' => 'sometimes|nullable|string|max:100',
            'gpt_rate_limit' => 'sometimes|integer|min:1|max:1000',
            'allows_test_reveals' => 'sometimes|boolean',
            'bypass_similarity_check' => 'sometimes|boolean',
            'ai_authorship_check' => 'sometimes|boolean',
            'test_config' => 'sometimes|array',
        ])->validate();
        $legacyFormat = in_array($data['language'], ['ipynb', 'zip', 'image', 'xlsx', 'github'], true) ? $data['language'] : null;
        if ($legacyFormat) $data['language'] = $legacyFormat === 'ipynb' ? 'python' : 'text';
        $config = array_intersect_key($data, array_flip(['language', 'check_type', 'test_mode', 'private_instructions', 'reference_answer', 'gpt_model', 'gpt_rate_limit']));
        $config['private_instructions'] = $data['private_instructions'] ?? '';
        $config['bypass_similarity_check'] = (bool) ($data['bypass_similarity_check'] ?? false);
        $config['ai_authorship_check'] = (bool) ($data['ai_authorship_check'] ?? true);
        $config['submission_modes'] = array_values($data['submission_modes'] ?? ($legacyFormat ? [$legacyFormat === 'github' ? 'repository' : 'files'] : ($data['language'] === 'text' ? ['text', 'files'] : ['code', 'files'])));
        $config['allowed_languages'] = $data['allowed_languages'] ?? [$data['language']];
        abort_unless(in_array($data['language'], $config['allowed_languages'], true), 422, 'Default language must be allowed.');
        $config['allowed_extensions'] = array_map('strtolower', $data['allowed_extensions'] ?? match ($legacyFormat) {
            'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp'], 'xlsx' => ['xlsx'], 'zip' => ['zip'], 'ipynb' => ['ipynb'], default => []});
        $config['max_files'] = (int) ($data['max_files'] ?? 20);
        $config['max_file_bytes'] = (int) ($data['max_file_bytes'] ?? 52428800);
        $config['max_total_bytes'] = (int) ($data['max_total_bytes'] ?? 52428800);
        $config['entrypoint'] = isset($data['entrypoint']) ? Paths::validate($data['entrypoint']) : null;
        $config['test_mode'] = $data['test_mode'] ?? 'io';
        abort_if($data['check_type'] === 'tests' && $config['test_mode'] === 'environment', 422,
            'Legacy environments must be converted to data-only IO tests before native submissions are enabled.');
        if ($data['check_type'] === 'tests' && $config['test_mode'] === 'io') {
            abort_unless(in_array($data['language'], ['cpp', 'python'], true), 422, 'IO tests require Python or C++.');
            abort_unless(!array_diff($config['allowed_languages'], ['cpp', 'python'])
                && !in_array('text', $config['submission_modes'], true), 422, 'Tests require executable source files.');
            $config['test_config'] = $this->io($data['test_config'] ?? [], (int) $task->max_mark);
        }
        if ($data['check_type']==='tests' && $config['test_mode']==='suite') {
            $config['test_config']=app(LegacySuites::class)->validate($data['test_config']??[],$config['language'],(int)$task->max_mark);
            $existing=CodeTaskChecker::find($task->id);
            abort_unless($existing && ($existing->config['test_mode']??null)==='suite'
                && $config['test_config']===$existing->config['test_config'],422,'Checking suites are installed only by the reviewed migration.');
            abort_unless($config['allowed_languages']===[$config['language']] && !in_array('text',$config['submission_modes'],true),422);
        }
        $task->update(['is_code' => $config['check_type'] !== 'manual']);
        return CodeTaskChecker::updateOrCreate(['task_id' => $task->id], [
            'config' => $config, 'revision' => hash('sha256', json_encode($config, JSON_THROW_ON_ERROR)),
            'allows_test_reveals' => (bool) ($data['allows_test_reveals'] ?? true), 'updated_by' => $actor,
        ]);
    }

    public function io(array $input, int $maximum, ?callable $legacyField = null): array
    {
        $config = array_replace([
            'comparison' => 'tokens', 'trailing_whitespace' => 'one_newline', 'scoring' => 'proportional',
            'time_limit' => 2, 'output_limit' => 8388608, 'memory_limit' => 512, 'process_limit' => 128,
            'disable_swap' => false, 'absolute_tolerance' => '0.000001', 'relative_tolerance' => '0.000001',
        ], $input);
        Validator::make($config, [
            'comparison' => ['required', Rule::in(self::COMPARISONS)],
            'trailing_whitespace' => ['required', Rule::in(['one_newline', 'newlines', 'all'])],
            'scoring' => ['required', Rule::in(['proportional', 'all_or_nothing', 'groups'])],
            'time_limit' => 'required|integer|min:1|max:30', 'output_limit' => 'required|integer|min:1024|max:8388608',
            'memory_limit' => 'required|integer|min:128|max:2048', 'process_limit' => 'required|integer|min:16|max:256',
            'disable_swap' => 'required|boolean',
            'absolute_tolerance' => 'required|numeric|min:0|max:1', 'relative_tolerance' => 'required|numeric|min:0|max:1',
            'tests' => 'required|array|min:1|max:50', 'tests.*' => 'required|array',
            'tests.*.is_public' => 'required|boolean',
            'tests.*.comparison' => ['sometimes', Rule::in(self::COMPARISONS)],
            'tests.*.time_limit' => 'sometimes|integer|min:1|max:30',
            'tests.*.output_limit' => 'sometimes|integer|min:1024|max:8388608',
        ])->validate();
        $total = 0;
        $tests = [];
        foreach ($config['tests'] as $test) {
            $row = array_intersect_key($test, array_flip(['comparison', 'time_limit', 'output_limit', 'group']));
            $row['is_public'] = (bool) $test['is_public'];
            foreach (['time_limit', 'output_limit'] as $key) if (isset($row[$key])) $row[$key] = (int) $row[$key];
            foreach (['input', 'output'] as $key) {
                $value = $test[$key] ?? null;
                if (is_string($value)) {
                    $value = str_replace(["\r\n", "\r"], "\n", $value);
                    abort_if(strlen($value) > Artifacts::MAX_TEST_BYTES, 422, 'Test field exceeds 32 MiB.');
                    $value = $legacyField ? $legacyField($value) : $this->artifacts->put($value);
                } else {
                    $value = $legacyField ? $legacyField($value) : $this->artifacts->descriptor($value, true);
                }
                abort_if($value['truncated'], 422, 'Test input and expected output must be complete.');
                if (!$legacyField && isset($value['blob_id'])) abort_if(app(LegacySuites::class)->isBundleArtifact($value['blob_id']),422,'Internal checker packages cannot be used as test data.');
                $row[$key] = $value;
                $total += $value['bytes'];
            }
            $tests[] = $row;
        }
        abort_if($total > 268435456, 422, 'Test suite exceeds 256 MiB.');
        $result = array_intersect_key($config, array_flip(['comparison', 'trailing_whitespace', 'scoring', 'time_limit',
            'output_limit', 'memory_limit', 'process_limit', 'disable_swap', 'absolute_tolerance', 'relative_tolerance']));
        foreach (['time_limit', 'output_limit', 'memory_limit', 'process_limit'] as $key) $result[$key] = (int) $result[$key];
        $result['disable_swap'] = (bool) $result['disable_swap'];
        foreach (['absolute_tolerance', 'relative_tolerance'] as $key) $result[$key] = (string) $result[$key];
        if ($result['scoring'] === 'groups') {
            Validator::make($config, ['groups' => 'required|array|min:1|max:50',
                'groups.*.id' => 'required|string|regex:/^[a-zA-Z0-9_-]{1,64}$/D|distinct',
                'groups.*.name' => 'required|string|max:100', 'groups.*.points' => 'required|integer|min:1|max:1000000'])->validate();
            abort_unless(array_sum(array_column($config['groups'], 'points')) === $maximum, 422, 'Group points must equal task maximum.');
            $groups = array_column($config['groups'], 'id');
            foreach ($tests as $test) abort_unless(in_array($test['group'] ?? null, $groups, true), 422, 'Invalid test group.');
            abort_unless(count(array_unique(array_column($tests, 'group'))) === count($groups), 422, 'Every group needs a test.');
            $result['groups'] = array_map(fn ($group) => ['id' => $group['id'], 'name' => $group['name'], 'points' => (int) $group['points']], $config['groups']);
        }
        $result['tests'] = $tests;
        return $result;
    }

    public function snapshot(Task $task, CodeTaskChecker $checker): array
    {
        return array_merge($checker->config, ['task_id' => (int) $task->id, 'name' => $task->name,
            'public_text' => $task->text, 'points' => (int) $task->max_mark, 'revision' => $checker->revision,
            'task_files' => \App\CodeTaskFile::where('task_id', $task->id)->orderBy('id')->get()->map(fn ($file) => [
                'id' => $file->id, 'name' => $file->path, 'original_name'=>$file->original_name, 'mime_type'=>$file->mime_type, 'kind' => $file->kind, 'is_public' => $file->is_public,
                'read_only' => $file->read_only, 'include_in_check' => $file->include_in_check, 'artifact' => $file->artifact])->all()]);
    }

    public function publicSettings(CodeTaskChecker $checker): array
    {
        return array_intersect_key($checker->config, array_flip(['language', 'check_type', 'test_mode', 'submission_modes',
            'allowed_languages', 'allowed_extensions', 'max_files', 'max_file_bytes', 'max_total_bytes', 'entrypoint']))
            + ['allows_test_reveals' => $checker->allows_test_reveals, 'max_source_bytes' => 2097152,
                'max_file_bytes' => 52428800, 'max_files' => 20, 'schema_version' => 2,
                'requires_conversion' => (bool) ($checker->config['requires_conversion'] ?? false)];
    }
}
