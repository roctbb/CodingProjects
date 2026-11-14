<?php

namespace App\Http\Controllers;

use App\CodeExecutionRun;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Reports;
use App\Services\CodeChecking\Results;
use App\Services\CodeChecking\ServiceToken;
use App\Services\CodeChecking\Submissions;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class CodeCheckerAPI extends Controller
{
    public function input(Request $request, string $execution, ServiceToken $tokens, Submissions $submissions)
    {
        $tokens->verify($request, 'execution-input', false);
        $run = CodeExecutionRun::findOrFail($execution);
        return response()->json($submissions->input($run))->header('Cache-Control', 'private, no-store');
    }

    public function progress(Request $request, string $execution, ServiceToken $tokens, Results $results)
    {
        $tokens->verify($request, 'execution-progress');
        $results->progress(CodeExecutionRun::findOrFail($execution), $this->body($request));
        return response()->json(['state' => 'ok']);
    }

    public function result(Request $request, string $execution, ServiceToken $tokens, Results $results)
    {
        $tokens->verify($request, 'execution-result');
        $results->accept(CodeExecutionRun::findOrFail($execution), $this->body($request), hash('sha256', $request->getContent()));
        return response()->json(['state' => 'ok', 'execution_id' => $execution]);
    }

    public function report(Request $request, ServiceToken $tokens, Reports $reports)
    {
        $tokens->verify($request, 'test-report');
        $record = $reports->accept($this->body($request, 262144));
        return response()->json(['state' => 'ok', 'run_id' => $record->id]);
    }

    public function artifactStatus(Request $request, ServiceToken $tokens, Artifacts $artifacts)
    {
        $tokens->verify($request, 'test-artifacts');
        $data = $this->body($request, 20000);
        abort_unless(is_array($data['ids'] ?? null) && count($data['ids']) <= 200, 422);
        $missing = [];
        foreach ($data['ids'] as $digest) {
            abort_unless(is_string($digest) && preg_match('/^[a-f0-9]{64}$/D', $digest), 422);
            if (!$artifacts->exists($digest)) $missing[] = $digest;
        }
        return response()->json(['missing' => $missing]);
    }

    public function uploadArtifact(Request $request, string $artifact, ServiceToken $tokens, Artifacts $artifacts)
    {
        $tokens->verify($request, 'test-artifact-upload');
        abort_unless(strlen($request->getContent()) <= Artifacts::MAX_TEST_BYTES
            && hash_equals($artifact, hash('sha256', $request->getContent())), 422);
        return response()->json($artifacts->put($request->getContent()));
    }

    public function artifact(Request $request, string $artifact, Artifacts $artifacts, ServiceToken $tokens)
    {
        try {
            $claims = JWT::decode((string) $request->query('token'), new Key($tokens->key(), 'HS256'));
            abort_unless(($claims->iss ?? null) === 'codingprojects' && ($claims->aud ?? null) === 'codingprojects'
                && ($claims->purpose ?? null) === 'execution-artifact' && ($claims->blob_id ?? null) === $artifact
                && is_int($claims->iat ?? null) && is_int($claims->exp ?? null) && $claims->exp > $claims->iat
                && $claims->exp - $claims->iat <= 300 && is_string($claims->execution_id ?? null), 401);
            $run = CodeExecutionRun::findOrFail($claims->execution_id);
            $ids = [];
            $visit = function ($value) use (&$visit, &$ids) {
                if (!is_array($value)) return;
                if (($value['type'] ?? null) === 'blob') $ids[] = $value['blob_id'];
                else foreach ($value as $item) $visit($item);
            };
            $visit($run->snapshot);
            $visit($run->submission->files);
            abort_unless(in_array($artifact, $ids, true), 401);
        } catch (\Exception $exception) {
            abort(401);
        }
        abort_unless($artifacts->exists($artifact), 404);
        return $artifacts->download(['blob_id'=>$artifact,'bytes'=>(int) \Illuminate\Support\Facades\DB::table('code_artifacts')->where('id',$artifact)->value('bytes')], 'data.bin', false);
    }

    private function body(Request $request, int $maximum = 524288): array
    {
        abort_if(strlen($request->getContent()) > $maximum, 413);
        try {
            $data = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            abort(422, 'Invalid JSON.');
        }
        abort_unless(is_array($data), 422);
        return $data;
    }
}
