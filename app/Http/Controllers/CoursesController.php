<?php

namespace App\Http\Controllers;

use App\ActionLog;
use App\CompletedCourse;
use App\Course;
use App\CourseCategory;
use App\Event;
use App\ForumThread;
use App\Idea;
use App\Program;
use App\ProgramChapter;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Provider;
use App\Solution;
use App\User;
use App\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class CoursesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('details', 'open_index');
        $this->middleware('course')->only(['details', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'createChapter', 'editChapter', 'createChapterView', 'editChapterView']);
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'createChapter', 'editChapter', 'createChapterView', 'editChapterView']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $users = User::where('is_hidden', false)->get();
        $courses = Course::orderBy('id')->get();
        $events = Event::getNew()->sortBy('date')->take(5);

        $my_courses = $courses->filter(function ($course) use ($user) {
            return $course->state == 'started' && ($user->role == 'admin' || $course->students->contains($user) || $course->teachers->contains($user));
        });

        $open_courses = $courses->filter(function ($course) use ($user) {
            return $course->state == 'started' && ($user->role != 'admin' && !$course->students->contains($user) && !$course->teachers->contains($user) && $course->is_open);
        });

        $private_courses = $courses->filter(function ($course) use ($user) {
            return $course->state == 'started' && ($user->role != 'admin' && !$course->students->contains($user) && !$course->teachers->contains($user) && !$course->is_open);
        });
        $notifications = collect([]);
        foreach ($user->unreadNotifications as $notification) {
            $notifications->push($notification);
            $notification->markAsRead();
        }

        $threads = ForumThread::orderBy('id', 'DESC')->limit(5)->get();
        return view('home', compact('courses', 'user', 'users', 'threads', 'my_courses', 'open_courses', 'private_courses', 'notifications', 'events'));
    }

    public function report($id)
    {
        $user = User::with('solutions', 'solutions.task', 'solutions.task.consequences')->findOrFail(Auth::User()->id);
        $course = Course::with('program.lessons', 'students', 'students.submissions', 'teachers', 'program.steps', 'program.lessons.prerequisites', 'program.lessons.info')->findOrFail($id);
        $students = $course->students;

        if (!$course->is_sdl) {
            $steps = $course->steps;

            $lessons = $course->lessons->filter(function ($lesson) use ($course) {
                return $lesson->isStarted($course);
            });

            $temp_steps = collect([]);
            foreach ($lessons as $lesson) {
                $temp_steps = $temp_steps->merge($lesson->steps);
            }

            /* count pulse */
            $ids = $students->pluck('id');
            $steps_ids = $temp_steps->pluck('id');


            $use_records = ActionLog::where('created_at', '>', Carbon::now()->addWeeks(-2))->whereIn('user_id', $ids)->get()->filter(function ($item) use ($steps_ids, $course) {
                return ($item->type == 'course' and $item->object_id == $course->id) or ($item->type == 'step' and $steps_ids->contains($item->object_id));
            })->groupBy('user_id')->map(function ($item) {
                return $item->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d');
                })->map(function ($item) {
                    return $item->count();
                });
            });

            $pulse_keys = collect([]);
            $pulse_values = collect([]);


            foreach ($use_records as $student_id => $value) {
                $first_hour = Carbon::createFromFormat('Y-m-d', $value->keys()[0]);

                while ($first_hour->lt(Carbon::now())) {
                    $first_hour->addHour();
                    if (!$value->has($first_hour->format('Y-m-d'))) {
                        $use_records[$student_id][$first_hour->format('Y-m-d')] = 0;
                    }
                }

                $use_records[$student_id] = $use_records[$student_id]->sortBy(function ($item, $key) {
                    return Carbon::createFromFormat('Y-m-d', $key);
                });

                $pulse_keys[$student_id] = '[\'' . implode('\', \'', $use_records[$student_id]->keys()->toArray()) . '\']';
                $pulse_values[$student_id] = '[\'' . implode('\', \'', $use_records[$student_id]->values()->toArray()) . '\']';
            }

            $task_records = $course->solutions->where('created_at', '>', Carbon::now()->addWeeks(-2))->groupBy('user_id')->map(function ($item) {
                return $item->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d');
                })->map(function ($item) {
                    return $item->sum('mark');
                });
            });

            $task_keys = collect([]);
            $task_values = collect([]);


            foreach ($task_records as $student_id => $value) {
                $first_hour = Carbon::now()->addWeeks(-2);

                while ($first_hour->lt(Carbon::now())) {
                    $first_hour->addDay();
                    if (!$value->has($first_hour->format('Y-m-d'))) {
                        $task_records[$student_id][$first_hour->format('Y-m-d')] = 0;
                    }
                }

                $task_records[$student_id] = $task_records[$student_id]->sortBy(function ($item, $key) {
                    return Carbon::createFromFormat('Y-m-d', $key);
                });

                $task_keys[$student_id] = '[\'' . implode('\', \'', $task_records[$student_id]->keys()->toArray()) . '\']';
                $task_values[$student_id] = '[\'' . implode('\', \'', $task_records[$student_id]->values()->toArray()) . '\']';
            }


            foreach ($students as $key => $value) {
                $students[$key]->percent = 0;
                $students[$key]->max_points = 0;
                $students[$key]->points = 0;
                foreach ($temp_steps as $step) {
                    if ($value->pivot->is_remote) {
                        $tasks = $step->remote_tasks;
                    } else {
                        $tasks = $step->class_tasks;
                    }

                    foreach ($tasks as $task) {
                        if (!$task->is_star) $students[$key]->max_points += $task->max_mark;
                        $students[$key]->points += $value->submissions->where('task_id', $task->id)->max('mark');
                    }


                }
                if ($students[$key]->max_points != 0) {
                    $students[$key]->percent = min(100, $students[$key]->points * 100 / $students[$key]->max_points);
                }
            }
            return view('courses.report', compact('course', 'user', 'steps', 'students', 'lessons', 'pulse_keys', 'pulse_values', 'task_keys', 'task_values'));
        } else {
            $lessons = collect([]);
            $student_data = collect([]);
            foreach ($students as $student) {
                $lessons[$student->id] = $course->user_sdl_lessons($student)->get();

                $temp_steps = collect([]);
                foreach ($lessons[$student->id] as $lesson) {
                    $temp_steps = $temp_steps->merge($lesson->steps);
                }

                $student_data[$student->id] = $student;
                $student_data[$student->id]->percent = 0;
                $student_data[$student->id]->max_points = 0;
                $student_data[$student->id]->points = 0;
                foreach ($temp_steps as $step) {
                    $tasks = $step->class_tasks;

                    foreach ($tasks as $task) {
                        if (!$task->is_star) $student_data[$student->id]->max_points += $task->max_mark;
                        $student_data[$student->id]->points += $student->submissions->where('task_id', $task->id)->max('mark');
                    }
                }
                if ($student_data[$student->id]->max_points != 0) {
                    $student_data[$student->id]->percent = min(100, $student_data[$student->id]->points * 100 / $student_data[$student->id]->max_points);
                }


            }
            return view('courses.sdl_report', compact('course', 'user', 'students', 'lessons', 'student_data'));


        }

    }

    public function details($id, Request $request)
    {
        \App\ActionLog::record(Auth::User()->id, 'course', $id);

        $user = User::with('solutions', 'solutions.task', 'solutions.task.consequences')->findOrFail(Auth::User()->id);
        $course = Course::with('program.lessons', 'program.chapters', 'students', 'students.submissions', 'teachers', 'program.lessons.steps', 'program.lessons.steps.class_tasks', 'program.lessons.prerequisites', 'program.lessons.info')->findOrFail($id);
        $students = $course->students;

        //dd($course);


        if (!$course->is_sdl) {
            $marks = CompletedCourse::where('course_id', $id)->get();

            //Made this due to some issues on my local server
            $cstudent = [];
            if ($request->has('chapter')) {
                $chapter = ProgramChapter::findOrFail($request->chapter);
            } else {
                if ($user->role == 'teacher') {
                    $chapter = $course->program->chapters->first();
                } else {
                    $current_chapter = $course->program->chapters->first();
                    foreach ($course->program->chapters as $chapter) {
                        $current_chapter = $chapter;
                        if (!$chapter->isDone($course)) {
                            break;
                        }
                    }
                    $chapter = $current_chapter;
                }
            }

            $temp_steps = collect([]);
            $all_steps = collect([]);
            $lessons = $course->lessons->filter(function ($lesson) use ($course, $chapter) {
                return $lesson->isStarted($course) and $lesson->chapter_id == $chapter->id;
            });


            foreach ($lessons as $lesson) {
                $temp_steps = $temp_steps->merge($lesson->steps);
            }
            foreach ($course->lessons->filter(function ($item) use ($course) {
                return $item->isStarted($course);
            }) as $lesson) {
                $all_steps = $all_steps->merge($lesson->steps);
            }

            if (count($students) < 15) {
                foreach ($students as $key => $value) {
                    $students[$key]->percent = 0;
                    $students[$key]->max_points = 0;
                    $students[$key]->points = 0;

                    foreach ($all_steps as $step) {

                        $tasks = $step->class_tasks;

                        foreach ($tasks as $task) {
                            if (!$task->is_star) $students[$key]->max_points += $task->max_mark;

                            $students[$key]->points += $value->submissions->filter(function ($item) use ($task) {
                                return $item->task_id == $task->id;
                            })->max('mark');

                        }


                    }
                    if ($students[$key]->max_points != 0) {
                        $students[$key]->percent = min(100, $students[$key]->points * 100 / $students[$key]->max_points);
                    }

                }
            }


            if ($course->students->contains($user)) {
                $lessons = $course->lessons->filter(function ($lesson) use ($course, $chapter) {
                    return $lesson->isStarted($course) and $lesson->chapter_id == $chapter->id;
                });

                $steps = $temp_steps;
                $cstudent = $students->filter(function ($value, $key) use ($user) {
                    return $value->id == $user->id;
                })->first();
            } else {
                $steps = $temp_steps;
                $lessons = $course->lessons->where('chapter_id', $chapter->id);
            }

            return view('courses.details', compact('chapter', 'course', 'user', 'steps', 'students', 'cstudent', 'lessons', 'marks'));

        } else {
            if ($user->role != 'student')
                return redirect('insider/courses/' . $id . '/report');

            $student = $students->where('id', $user->id)->first();
            $idea = null;
            if ($student->pivot->idea_id != null) {
                $idea = Idea::findOrFail($student->pivot->idea_id);
                $idea_lessons = $course->user_sdl_lessons($user)->where('sdl_node_id', $idea->sdl_node_id)->get();
                foreach ($idea_lessons as $lesson) {
                    if ($lesson->percent($user) > 90) {
                        $idea = null;
                        break;
                    }
                }

            }

            $marks = [];
            $lessons = $course->user_sdl_lessons($user)->get();

            $done_lessons = $lessons->filter(function ($item) use ($user) {
                return $item->percent($user) > 90;
            });
            $current_lessons = $lessons->filter(function ($item) use ($user) {
                return $item->percent($user) <= 90;
            });
            $available_lessons = Lesson::getAvailableSdlLessons($user, $course, $idea);

            $available_lessons = $current_lessons->merge($available_lessons);
            return view('courses.sdl_details', compact('course', 'user', 'lessons', 'marks', 'students', 'available_lessons', 'done_lessons', 'idea'));
        }


    }

    public function setSdlIdea($id, Request $request)
    {
        $course = Course::findOrFail($id);
        $student = $course->students->where('id', Auth::User()->id)->first();

        if (!$request->has('idea_id')) {
            $student->pivot->idea_id = null;
        } else {
            $this->validate($request, [
                'idea_id' => 'required|exists:ideas,id',
            ]);
            $idea = Idea::findOrFail($request->idea_id);
            if ($idea->sdl_node_id == null) abort(503);
            $student->pivot->idea_id = $idea->id;
        }

        $student->pivot->save();

        return redirect('/insider/courses/' . $course->id . '');
    }

    public function addSdlLesson($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $course = Course::findOrFail($id);
        $lesson = Lesson::findOrFail($request->lesson_id);

        if (!$course->is_sdl) abort(422);
        if ($lesson->sdl_node_id == null) abort(422);
        if (!$course->students->contains($user) and !$course->teachers->contains($user)) {
            abort(422);
        }

        $course->sdl_lessons()->attach($request->lesson_id, array('user_id' => $user->id));
        return redirect('/insider/courses/' . $course->id);

    }

    public function assessments($id)
    {
        $course = Course::findOrFail($id);
        return view('courses.assessments', compact('course'));
    }

    public function createView()
    {
        $programs = Program::all();
        return view('courses.create', compact('programs'));
    }

    public function editView($id)
    {
        $course = Course::findOrFail($id);
        return view('courses.edit', compact('course'));
    }

    public function start($id)
    {
        $course = Course::findOrFail($id);
        $course->start();
        return redirect('/insider/courses/' . $course->id);
    }

    public function stop($id)
    {
        $course = Course::findOrFail($id);
        $course->end();
        return redirect('/insider/courses/' . $course->id);
    }

    public function createChapterView($course_id)
    {
        return view('courses.create_chapter');
    }

    public function editChapterView($course_id, $chapter_id)
    {
        $chapter = ProgramChapter::findOrFail($chapter_id);
        return view('courses.edit_chapter', compact('chapter'));
    }

    public function editChapter($course_id, $chapter_id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $chapter = ProgramChapter::findOrFail($chapter_id);


        $this->validate($request, [
            'name' => 'required|string',

        ]);

        $chapter = ProgramChapter::findOrFail($chapter_id);
        $chapter->name = $request->name;
        $chapter->description = clean($request->description);
        $chapter->save();

        return redirect('/insider/courses/' . $course_id . '?chapter=' . $chapter_id);
    }

    public function createChapter($course_id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);


        $this->validate($request, [
            'name' => 'required|string'
        ]);

        $course = Course::findOrFail($course_id);
        $program = $course->program;

        $order = 100;
        if ($program->chapters->count() != 0)
            $order = $program->chapters->last()->sort_index + 1;

        $chapter = new ProgramChapter();
        $chapter->name = $request->name;
        $chapter->program_id = $program->id;
        $chapter->sort_index = $order;
        $chapter->description = clean($request->description);

        $chapter->save();

        return redirect('/insider/courses/' . $course_id);
    }

    public function makeChapterLower($course_id, $chapter_id, Request $request)
    {
        $chapter = ProgramChapter::findOrFail($chapter_id);
        $chapter->sort_index -= 1;
        $chapter->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $chapter_id);
    }

    public function makeChapterUpper($course_id, $chapter_id, Request $request)
    {
        $chapter = ProgramChapter::findOrFail($chapter_id);
        $chapter->sort_index += 1;
        $chapter->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $chapter_id);
    }

    public function edit($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
        ]);

        $course = Course::findOrFail($id);
        $course->name = $request->name;
        $course->description = clean($request->description);
        $course->git = $request->git;
        $course->site = $request->site;
        $course->image = $request->image;
        $course->telegram = $request->telegram;
        $course->start_date = $request->start_date;
        $course->weekdays = $request->weekdays ? $request->weekdays : "";
        if (\Auth::user()->role == 'admin') {
            foreach ($course->teachers as $teacher) {
                $course->teachers()->detach($teacher->id);
            }
            if ($request->teachers != null) {
                foreach ($request->teachers as $teacher_id) {
                    $course->teachers()->attach($teacher_id);
                }
            }

            foreach ($course->categories as $category) {
                $course->categories()->detach($category->id);
            }
            if ($request->categories != null) {
                foreach ($request->categories as $category_id) {
                    $course->categories()->attach($category_id);
                }
            }
            if ($request->mode != null)
                $course->mode = $request->mode;
        }

        foreach ($course->students as $teacher) {
            $course->students()->detach($teacher->id);
        }
        if ($request->students != null)
            foreach ($request->students as $student_id) {
                $course->students()->attach($student_id);

                if (!$course->is_open) {
                    $user = User::findOrFail($student_id);
                    if ($user->role == 'novice') {
                        $user->role = 'student';
                        $user->save();
                    }
                }
            }


        if ($course->invite != $request->invite) {
            $this->validate($request, [
                'invite' => 'required|string|unique:courses,invite|unique:providers,invite',
            ]);
            $course->invite = $request->invite;
            $course->remote_invite = $request->invite . '-R';
        }


        /*if ($request->hasFile('image')) {
            $extn = '.' . $request->file('image')->guessClientExtension();
            $path = $request->file('image')->storeAs('course_avatars', $course->id . $extn);
            $course->image = $path;

        }*/
        if ($request->hasFile('import')) {
            $json = file_get_contents($request->file('import')->getRealPath());

            $course->import($json);
        }

        $course->save();
        return redirect('/insider/courses/' . $course->id);
    }

    public function create(Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);


        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'image' => 'image|max:1000',
        ]);

        $course = new Course();
        if ($request->program >= 0) {
            $this->validate($request, ['program' => 'required|integer|exists:programs,id']);
            $course->program_id = $request->program;
        } else {
            if ($request->program == -1) {
                $program = new Program();
                $program->name = $request->name;
                $program->save();

                $course->program_id = $program->id;

                $order = 100;
                if ($program->chapters->count() != 0)
                    $order = $program->chapters->last()->sort_index + 1;

                $chapter = new ProgramChapter();
                $chapter->name = $request->name;
                $chapter->program_id = $program->id;
                $chapter->sort_index = $order;
                $chapter->save();
            } else if ($request->program == -2) {
                $this->validate($request, ['sdl_version' => 'required|integer|exists:core_nodes,version']);
                $course->is_sdl = true;
                $course->sdl_core_version = $request->sdl_version;
                $course->program_id = null;
            } else {
                abort(422);
            }

        }


        $course->name = $request->name;
        $course->description = clean($request->description);
        $course->save();
        $course->teachers()->attach($user->id);


        if ($request->hasFile('image')) {
            $extn = '.' . $request->file('image')->guessClientExtension();
            $path = $request->file('image')->storeAs('course_avatars', $course->id . $extn);
            $course->image = $path;

        } else {
            $course->image = 'course_avatars/blank.png';
        }
        $course->provider_id = $user->provider_id;
        $course->save();


        return redirect('/insider/courses');
    }


    public function invite(Request $request)
    {
        if ($request->invite == null || $request->invite == "") {
            $this->make_error_alert('Ошибка!', 'Курс с таким приглашением не найден.');
            return $this->backException();
        }
        $user = User::findOrFail(Auth::User()->id);
        $course = Course::where('invite', $request->invite)->first();
        $remote = false;
        if ($course == null) {
            $course = Course::where('remote_invite', $request->invite)->first();
            $remote = true;
        }

        if ($course == null) {
            $this->make_error_alert('Ошибка!', 'Курс с таким приглашением не найден.');
            return $this->backException();
        }

        if ($course->students->contains($user)) {
            $this->make_error_alert('Ошибка!', 'Вы уже зачислены на курс "' . $course->name . '".');
            return $this->backException();
        }
        if ($user->role == 'novice') {
            $user->role = 'student';
            $user->save();
        }

        $this->make_success_alert('Успех!', 'Вы присоединились к курсу "' . $course->name . '".');
        $course->students()->attach([$user->id => ['is_remote' => $remote]]);


        return redirect()->back();
    }

    public function enroll($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $course = Course::findOrFail($id);

        if ($user->role == 'admin' or $user->role == 'teacher') return redirect('/insider/courses/' . $course->id);

        if ($course == null or $course->mode != 'open') {
            $this->make_error_alert('Ошибка!', 'Вы не можете записаться на приватный курс.');
            return $this->backException();
        }

        if ($course->students->contains($user)) {
            $this->make_error_alert('Ошибка!', 'Вы уже зачислены на курс "' . $course->name . '".');
            return $this->backException();
        }
        $this->make_success_alert('Успех!', 'Вы присоединились к курсу "' . $course->name . '".');
        $course->students()->attach([$user->id => ['is_remote' => false]]);


        return redirect('/insider/courses/' . $course->id);
    }

    public function export($id)
    {
        $course = Course::findOrFail($id);

        $json = $course->export();

        $response = \Response::make($json);
        $response->header('Content-Type', 'application/json');
        $response->header('Content-length', strlen($json));
        $response->header('Content-Disposition', 'attachment; filename=course-' . $id . '.json');

        return $response;

    }
}
