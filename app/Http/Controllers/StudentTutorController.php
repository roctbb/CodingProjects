<?php

namespace App\Http\Controllers;

use App\Services\Tutor\Conversation;
use Illuminate\Http\Request;

final class StudentTutorController extends Controller
{
    public function __invoke(Request $request, int $course_id, int $id, Conversation $conversation)
    {
        $data = $request->validate([
            'request_id' => 'required|uuid', 'question' => 'required|string|max:1200',
            'task_id' => 'nullable|integer|required_with:intent', 'run_id' => 'nullable|uuid|required_with:submission_id',
            'submission_id' => 'nullable|uuid|required_with:run_id',
            'intent' => 'nullable|in:hint,submission|prohibits:run_id',
            'focus' => 'nullable|string|max:120', 'excerpt' => 'nullable|string|max:1400',
        ]);
        $reply = $conversation->ask($request->user(), $course_id, $id, $data);
        unset($reply['good_question']);
        return response()->json($reply)->header('Cache-Control', 'private, no-store');
    }
}
