<?php

namespace App\Http\Controllers;

use App\Services\CodeChecking\Access;
use App\Services\TaskChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskChatController extends Controller
{
    public function thread(Request $request, int $course, int $task, int $student, Access $access, TaskChat $chat)
    {
        $viewer = $request->user();
        $assignment = $access->task($viewer, $course, $task);
        if ($student !== (int) $viewer->id) {
            abort_unless($access->teacher($viewer, $course), 403);
            abort_unless(DB::table('course_students')->where(['course_id'=>$course, 'user_id'=>$student])->exists(), 404);
        }

        if ($request->isMethod('post')) {
            $input = $request->validate([
                'body' => ['required', 'string', 'max:3000'],
                'run_id' => ['nullable', 'uuid', 'required_with:tutor_action'],
                'attempt' => ['nullable', 'string', 'required_with:tutor_action', 'regex:/^(?:solution-[1-9][0-9]{0,17}|[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})$/D'],
                'tutor_action' => ['nullable', 'in:examples'],
                'chat_layout' => ['nullable', 'in:panel,dialog'],
                'client_id' => ['required', 'uuid'],
            ]);
            $withTutor = $student === (int)$viewer->id && !$assignment->is_quiz
                && \App\Services\Tutor\Context::enabledForCourse($course)
                && ($viewer->role === 'student' || $access->teacher($viewer, $course));
            $chat->send($course, $task, $student, (int) $viewer->id, $input, $withTutor);
            return response()->json(['saved'=>true], 200);
        }

        if ($request->boolean('socket_auth')) return response()->noContent()->header('Cache-Control', 'private, no-store');
        $request->validate(['before'=>['nullable','string','max:1024']]);
        if ($request->filled('before')) {
            $cursor = json_decode(base64_decode(strtr($request->input('before'), '-_', '+/'), true) ?: '', true);
            abort_unless(is_array($cursor) && ($cursor['_pointsToNextItems'] ?? null) === true, 422);
            validator($cursor, [
                'happened_at'=>['required','date'],
                'kind'=>['required','integer','between:1,5'],
                'event_id'=>['required','string','regex:/^[0-9a-f-]+ *$/Di'],
            ])->validate();
        }
        $page = $chat->page($course, $assignment, $student, $viewer->role === 'student' && (int)$viewer->id === $student);
        if ((int)$viewer->id === $student && !$request->filled('before')) {
            $page['awaiting_tutor'] = $chat->awaitingTutor($course, $task, $student);
        }
        if ((int)$viewer->id !== $student) {
            $page['awaiting_reply'] = $chat->waitingStudents($course, [$task], $student)->isNotEmpty();
        }
        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }
}
