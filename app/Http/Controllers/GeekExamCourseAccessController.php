<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeekExamCourseAccessController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $secret = (string) config('services.geekexam_course_access_secret');
        abort_if(strlen($secret) < 32, 503);
        $body = $request->getContent();
        abort_if(strlen($body) > 32768, 413);
        $stamp = (string) $request->header('X-Exam-Time');
        abort_unless(ctype_digit($stamp) && strlen($stamp) === 10 && abs(now()->timestamp - (int) $stamp) <= 60, 401);
        $signature = hash_hmac('sha256', "POST\n/api/geekexam/course-access\n".$stamp."\n".$body, $secret);
        abort_unless(hash_equals($signature, (string) $request->header('X-Exam-Signature')), 401);

        $data = validator($request->json()->all(), [
            'active' => 'present|array|list|max:200', 'active.*' => 'array:id,user_id',
            'active.*.id' => 'required|uuid|distinct', 'active.*.user_id' => 'required|integer|min:1',
            'release' => 'present|array|list|max:200', 'release.*' => 'required|uuid|distinct',
            'reconcile' => 'required|boolean',
            'release_stale' => 'sometimes|array|list|max:200', 'release_stale.*' => 'required|uuid|distinct',
        ])->validate();
        $confirmed = DB::transaction(function () use ($data) {
            $table = DB::table('geekexam_course_restrictions');
            $at = now();
            $holds = collect($data['active'])->keyBy('id')->sortKeys();
            if ($holds->isNotEmpty()) {
                $table->insertOrIgnore($holds->map(fn ($hold) => $hold + ['confirmed_at' => $at, 'released_at' => null])->values()->all());
            }
            if ($data['release']) {
                // A delayed hold or renewal cannot reopen a finished attempt.
                $released = collect($data['release'])->sort()->map(fn ($id) =>
                    ['id' => $id, 'user_id' => null, 'confirmed_at' => $at, 'released_at' => $at])->values()->all();
                $table->upsert($released, ['id'], ['released_at']);
            }
            if (!empty($data['release_stale'])) {
                // A learner may have retried admission since GeekExam requested the stale list.
                (clone $table)->whereIn('id', $data['release_stale'])->whereNull('released_at')
                    ->where('confirmed_at', '<', $at->copy()->subMinutes(3))->update(['released_at' => $at]);
            }
            $active = (clone $table)->whereIn('id', $holds->keys())->whereNull('released_at');
            $records = (clone $active)->lockForUpdate()->pluck('user_id', 'id');
            foreach ($records as $id => $userId) {
                abort_unless((int) $holds[$id]['user_id'] === (int) $userId, 409);
            }
            $active->update(['confirmed_at' => $at]);
            return $records->keys()->all();
        }, 3);

        $stale = [];
        if ($data['reconcile']) {
            $table = DB::table('geekexam_course_restrictions');
            (clone $table)->where('released_at', '<', now()->subDay())->delete();
            $stale = $table->whereNull('released_at')->where('confirmed_at', '<', now()->subMinutes(3))
                ->orderBy('confirmed_at')->orderBy('id')->limit(200)->pluck('id')->all();
        }
        return response()->json(['confirmed' => $confirmed, 'stale' => $stale])->header('Cache-Control', 'no-store');
    }
}
