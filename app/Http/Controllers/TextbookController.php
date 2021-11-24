<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:22
 */

namespace App\Http\Controllers;

use App\Article;
use App\ArticleTag;
use App\CoinTransaction;
use App\Course;
use App\ForumComment;
use App\ForumPost;
use App\ForumTag;
use App\ForumThread;
use App\ForumVote;
use App\Lesson;
use App\Notifications\NewForumAnswer;
use App\Program;
use App\ProgramStep;
use App\Project;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;


class TextbookController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin')->only('edit');
    }

    public function index($id, Request $request)
    {
        $textbook = Program::findOrFail($id);

        if (!$textbook->available_as_textbook) {
            abort(404);
        }

        return view('rocket.textbook.index', compact('textbook'));
    }

    public function lesson($id, $lesson_id, Request $request)
    {
        $textbook = Program::findOrFail($id);

        if (!$textbook->available_as_textbook) {
            abort(404);
        }

        $lesson = Lesson::findOrFail($lesson_id);

        if ($id != $lesson->program_id) {
            abort(404);
        }

        $previous_id = null;
        $next_id = null;

        $textbook->lessons->sortBy(function ($lesson, $key) {
            return $lesson->sort_index + 10000 * $lesson->chapter->sort_index;
        }, SORT_REGULAR);

        dd($textbook->lessons);

        $lesson_index = $textbook->lessons->search(function($course_lesson) use ($lesson) {
            return $course_lesson->id == $lesson->id;
        });

        if ($lesson_index > 0) {
            $previous_id = $textbook->lessons[$lesson_index - 1]->id;
        }

        if ($lesson_index < count($textbook->lessons) - 1) {
            $next_id = $textbook->lessons[$lesson_index + 1]->id;
        }

        return view('rocket.textbook.lesson', compact('textbook', 'lesson', 'previous_id', 'next_id'));
    }

    public function edit_step($id, $step_id, Request $request)
    {
        $textbook = Program::findOrFail($id);
        $course = Course::where('program_id', $textbook->id)->first();

        return redirect('/insider/courses/'.$course->id.'/steps/'.$step_id.'/edit');
    }


}
