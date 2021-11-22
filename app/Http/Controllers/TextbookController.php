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
use App\ForumComment;
use App\ForumPost;
use App\ForumTag;
use App\ForumThread;
use App\ForumVote;
use App\Lesson;
use App\Notifications\NewForumAnswer;
use App\Program;
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
        $this->middleware('auth')->except('open_index', 'details');
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

        return view('rocket.textbook.lesson', compact('textbook', 'lesson'));
    }


}
