<?php

namespace App\Http\Controllers;

use App\Course;
use App\ProgramChapter;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\Program;
use App\Question;
use App\QuestionVariant;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;


class LessonsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'edit', 'makeLower', 'makeUpper', 'export']);

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function createView($id)
    {
        return view('lessons.create');
    }

    public function create($id, Request $request)
    {
        $program = Course::findOrFail($id)->program;
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
        ]);

        if ($request->has('chapter')) {
            $chapter = ProgramChapter::findOrFail($request->chapter);
        } else {
            $chapter = $program->chapters->first();
        }


        $order = 100;
        if ($program->lessons->count() != 0)
            $order = $program->lessons->last()->sort_index + 1;

        $lesson = new Lesson();
        $lesson->name = $request->name;
        $lesson->program_id = $program->id;
        $lesson->sort_index = $order;
        $lesson->description = clean($request->description);
        $lesson->sticker = "/stickers/" . random_int(1, 40) . ".png";
        $lesson->chapter_id = $chapter->id;

        $lesson->save();

        if ($request->prerequisites != null)
            foreach ($request->prerequisites as $prerequisite_id) {
                $lesson->prerequisites()->attach($prerequisite_id);
            }

        $data = ['name' => 'Введение', 'theory' => '', 'notes' => ''];

        $step = ProgramStep::createStep($lesson, $data);

        return redirect('/insider/courses/' . $id . '/steps/' . $step->id);
    }

    public function editView($course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $lesson = Lesson::findOrFail($id);
        return view('lessons.edit', compact('lesson', 'course'));
    }

    public function edit($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required',
            'start_date' => 'date|nullable',
            'chapter' => 'required|exists:program_chapters,id'
        ]);
        foreach ($lesson->prerequisites as $prerequisite) {
            $lesson->prerequisites()->detach($prerequisite->id);
        }
        if ($request->prerequisites != null)
            foreach ($request->prerequisites as $prerequisite_id) {
                $lesson->prerequisites()->attach($prerequisite_id);
            }
        $lesson->name = $request->name;
        $lesson->setStartDate($course, $request->start_date);
        $lesson->description = clean($request->description);
        $lesson->chapter_id = $request->chapter;
        if ($request->open == "yes")
            $lesson->is_open = true;
        else
            $lesson->is_open = false;

        if ($request->has('sdl_node_id')) {
            if ($request->sdl_node_id == -1) {
                $lesson->sdl_node_id = null;
            } else {
                $this->validate($request, ['sdl_node_id' => 'nullable|exists:core_nodes,id']);
                $lesson->sdl_node_id = $request->sdl_node_id;
                if ($request->has('is_sdl')) {
                    $lesson->is_sdl = true;
                } else {
                    $lesson->is_sdl = false;
                }
            }
        }

        if ($request->has('scale_id')) {
            if ($request->scale_id == -1) {
                $lesson->scale_id = null;
            } else {
                $this->validate($request, ['scale_id' => 'nullable|exists:result_scales,id']);
                $lesson->scale_id = $request->scale_id;
            }
        }

        $lesson->save();

        if ($request->hasFile('import') && $request->file('import')->getClientMimeType() == 'application/json') {
            $json = file_get_contents($request->file('import')->getRealPath());
            $lesson->import($json);
        }

        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function makeLower($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->sort_index -= 1;
        $lesson->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function makeUpper($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->sort_index += 1;
        $lesson->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function export($course_id, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $json = $lesson->export();

        $response = \Response::make($json);
        $response->header('Content-Type', 'application/json');
        $response->header('Content-length', strlen($json));
        $response->header('Content-Disposition', 'attachment; filename=lesson-' . $id . '.json');

        return $response;

    }


}
