<?php

namespace App\Http\Controllers;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTaskChecker;
use App\CodeTestReport;
use App\Services\CodeChecking\Access;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Configuration;
use App\Services\CodeChecking\Reports;
use App\Services\CodeChecking\RunnerClient;
use App\Services\CodeChecking\Submissions;
use Illuminate\Http\Request;

class CodeCheckingController extends Controller
{
    public function executorLogin(Request $request, \App\Services\CodeChecking\ServiceToken $tokens)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $url = rtrim((string) (config('services.geekexecutor_public_url')
            ?: config('services.geekexecutor_socket_url') ?: config('services.geekexecutor_url')), '/');
        $parts = parse_url($url);
        abort_unless($parts && in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']), 503, 'Executor public URL is not configured.');
        if (!$request->has('state')) {
            return redirect()->away($url.'/auth/login')->header('Cache-Control', 'no-store');
        }
        $state = $request->input('state');
        abort_unless(is_string($state) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $state), 422);
        $token = $tokens->issue('executor-login', 'geekexecutor', ['state' => $state,
            'user_id' => (int) $request->user()->id, 'role' => 'admin', 'name' => $request->user()->name]);
        // A fragment stays in the browser; it is never sent in a URL to the server or proxy logs.
        return redirect()->away($url.'/auth/callback#'.http_build_query(['token' => $token], '', '&', PHP_QUERY_RFC3986))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function executorStatus(Request $request, RunnerClient $runner)
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            return $this->json($runner->status());
        } catch (\Exception $exception) {
            return $this->json(['state' => 'unavailable', 'service' => 'GeekExecutor'], 502);
        }
    }

    public function settings(Request $request, int $course, int $task, Access $access, Configuration $configuration)
    {
        $access->task($request->user(), $course, $task);
        return $this->json($configuration->publicSettings(CodeTaskChecker::findOrFail($task)) + ['balance' => (int) $request->user()->balance(),
            'teacher_submission_allowed'=>$access->teacher($request->user(),$course),'viewer_user_id'=>(int)$request->user()->id]);
    }

    public function privateSettings(Request $request, int $course, int $task, Access $access, Configuration $configuration)
    {
        $model = $access->task($request->user(), $course, $task, true);
        if ($request->isMethod('put')) {
            try {
                $input = $request->isJson() ? json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR) : $request->all();
            } catch (\JsonException $exception) {
                abort(422, 'Invalid JSON.');
            }
            abort_unless(is_array($input), 422);
            $checker = $configuration->save($model, $input, (int) $request->user()->id);
        } else {
            $checker = CodeTaskChecker::findOrFail($task);
        }
        return $this->json(['config' => $checker->config, 'revision' => $checker->revision,
            'allows_test_reveals' => $checker->allows_test_reveals]);
    }

    public function uploadFixture(Request $request, int $course, int $task, Access $access, Artifacts $artifacts)
    {
        $access->task($request->user(), $course, $task, true);
        $request->validate(['file' => 'required|file|max:32768']);
        $artifact = $artifacts->putFile($request->file('file')->getRealPath(), false);
        \Illuminate\Support\Facades\DB::table('code_fixture_uploads')->insertOrIgnore(['task_id' => $task,
            'artifact_id' => $artifact['blob_id'], 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
        return $this->json($artifact + ['download_url' => route('code-fixtures.download', ['course' => $course, 'task' => $task, 'artifact' => $artifact['blob_id']])]);
    }

    public function extraAttempt(Request $request, int $course, int $task, Access $access, \App\Services\CodeChecking\Attempts $attempts)
    {
        return $this->json($attempts->buy($request->user(), $access->task($request->user(), $course, $task), $course));
    }

    public function index(Request $request, int $course, int $task, Access $access, Submissions $submissions)
    {
        $model = $access->task($request->user(), $course, $task);
        if ($request->isMethod('post')) {
            return $this->json($submissions->view($submissions->create($request, $course, $model), $request->user(),true,$this->testFields($request)), 201);
        }
        $request->validate(['user_id'=>'sometimes|integer|min:1','page'=>'sometimes|integer|min:1','teacher_viewed'=>'sometimes|boolean']);
        $teacher=$access->teacher($request->user(),$course);
        return $this->json(app(\App\Services\CodeChecking\History::class)->page($request->user(),$course,$model,
            $request->filled('user_id')?$request->integer('user_id'):null,
            $teacher && $request->has('teacher_viewed')?$request->boolean('teacher_viewed'):null,max(1,$request->integer('page',1))));
    }

    public function attempt(Request $request,int $course,int $task,int $solution,Access $access)
    {
        $model=$access->task($request->user(),$course,$task);
        return $this->json(app(\App\Services\CodeChecking\History::class)->event($request->user(),$course,$model,$solution));
    }

    public function show(Request $request, string $submission, Submissions $submissions,Access $access)
    {
        $record = CodeSubmission::findOrFail($submission);
        if (!$request->expectsJson()) {
            $access->authorOrTeacher($request->user(),$record);
            return redirect()->to(app(\App\Services\TaskLocation::class)->submission($record))->header('Cache-Control','private, no-store');
        }
        $record->load('currentRun','author');
        return $this->json($submissions->view($record, $request->user(),true,$this->testFields($request)));
    }

    public function subscription(Request $request, string $submission, Access $access, RunnerClient $runner)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        abort_if($record->currentRun->snapshot['check_type'] === 'manual', 409, 'Manual grading has no executor subscription.');
        return $this->json($runner->subscription($record->current_run_id, (int) $request->user()->id));
    }

    public function file(Request $request, string $submission, int $file, Access $access, Artifacts $artifacts)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        $item = $record->files[$file] ?? null;
        abort_unless($item, 404);
        return $artifacts->download($item['artifact'], $item['original_name'] ?? $item['name'], !$request->boolean('proxy'));
    }

    public function recheck(Request $request, string $submission, Submissions $submissions)
    {
        $request->validate(['latest_configuration' => 'sometimes|boolean', 'stronger_ai' => 'sometimes|boolean',
            'model' => 'prohibited', 'gpt_model' => 'prohibited', 'gpt_timeout' => 'prohibited']);
        $run = $submissions->recheck(CodeSubmission::findOrFail($submission), $request->user(), $request->boolean('latest_configuration'), $request->boolean('stronger_ai'));
        return $this->json(['execution_id' => $run->id, 'state' => $run->state], 202);
    }

    public function retry(Request $request, string $submission, Submissions $submissions)
    {
        $request->validate(['latest_configuration' => 'prohibited', 'stronger_ai' => 'prohibited', 'model' => 'prohibited',
            'gpt_model' => 'prohibited', 'gpt_timeout' => 'prohibited', 'code' => 'prohibited', 'files' => 'prohibited', 'text' => 'prohibited']);
        $run = $submissions->retry(CodeSubmission::findOrFail($submission), $request->user());
        return $this->json(['execution_id' => $run->id, 'state' => $run->state], 202);
    }

    public function report(Request $request, string $run, Reports $reports)
    {
        return $this->json($reports->view(CodeTestReport::findOrFail($run), $request->user(),$this->testFields($request)));
    }

    public function purchase(Request $request, string $run, string $test, Reports $reports)
    {
        $request->validate(['expected_price' => 'sometimes|integer|min:0']);
        return $this->json($reports->buy(CodeTestReport::findOrFail($run), $test, $request->user(),
            $request->has('expected_price') ? $request->integer('expected_price') : null,$this->testFields($request)));
    }

    public function download(Request $request, string $run, string $test, string $field, Reports $reports)
    {
        return $reports->download(CodeTestReport::findOrFail($run), $test, $field, $request->user());
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    private function testFields(Request $request):bool
    {
        $request->validate(['test_data'=>'sometimes|in:metadata,full']);
        return $request->input('test_data')!=='metadata';
    }
}
