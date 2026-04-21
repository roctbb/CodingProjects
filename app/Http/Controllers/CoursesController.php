<?php

namespace App\Http\Controllers;

use App\ActionLog;
use App\CompletedCourse;
use App\Course;
use App\CourseStudentPoints;
use App\Solution;
use App\Http\Requests\Courses\CreateChapterRequest;
use App\Http\Requests\Courses\InviteCourseRequest;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Requests\Courses\UpdateChapterRequest;
use App\Http\Requests\Courses\UpdateCourseRequest;
use App\LessonStudentStats;
use App\Program;
use App\ProgramChapter;
use App\Services\StudentProgressService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\User;
use App\Lesson;
use App\BlockedTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class CoursesController extends Controller
{
    private StudentProgressService $progressService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(StudentProgressService $progressService)
    {
        $this->progressService = $progressService;
        $this->middleware('auth')->except('details');
        $this->middleware('course')->only(['details', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'createChapter', 'editChapter', 'createChapterView', 'editChapterView', 'exportMarkdown', 'makeChapterLower', 'makeChapterUpper']);
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'createChapter', 'editChapter', 'createChapterView', 'editChapterView', 'exportMarkdown', 'makeChapterLower', 'makeChapterUpper']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Optimize: get authenticated user, cache users, eager load course relations
        $user = Auth::user();
        $users = Cache::remember('users:not_hidden', 60, function () {
            return User::where('is_hidden', false)->get();
        });
        $courses = Course::with(['students:id', 'teachers:id'])->orderBy('id')->get();

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

        $weekStart = Carbon::now()->subDays(7);

        $weeklySubmittedCount = Solution::where('user_id', $user->id)
            ->where('created_at', '>=', $weekStart)
            ->count();

        $weeklyCheckedCount = Solution::where('user_id', $user->id)
            ->where('created_at', '>=', $weekStart)
            ->whereNotNull('mark')
            ->count();

        $weeklyPoints = Solution::where('user_id', $user->id)
            ->where('created_at', '>=', $weekStart)
            ->whereNotNull('mark')
            ->sum('mark');

        $recentSubmissions = Solution::with(['task:id,step_id,name', 'course:id,name'])
            ->where('user_id', $user->id)
            ->whereNotNull('task_id')
            ->latest()
            ->limit(3)
            ->get();

        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(3)
            ->get();

        return response()->view('home', compact(
            'courses',
            'user',
            'users',
            'my_courses',
            'open_courses',
            'private_courses',
            'notifications',
            'weeklySubmittedCount',
            'weeklyCheckedCount',
            'weeklyPoints',
            'recentSubmissions',
            'recentNotifications'
        ));
    }

    public function report($id)
    {
        $user = User::with('solutions', 'solutions.task')->findOrFail(Auth::id());
        $course = Course::with('program.lessons', 'students', 'students.submissions', 'teachers', 'program.steps', 'program.lessons.info')->findOrFail($id);
        $students = $course->students;

        $steps = $course->steps;

            $lessons = $course->program->lessons->filter(function ($lesson) use ($course) {
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


        $this->applyCachedCoursePoints($id, $students);
        return view('courses.report', compact('course', 'user', 'steps', 'students', 'lessons', 'pulse_keys', 'pulse_values', 'task_keys', 'task_values'));

    }

    public function blocked($id)
    {
        $user = Auth::user();
        $course = Course::with('teachers', 'students')->findOrFail($id);
        if (!($user->role == 'admin' || $course->teachers->contains($user))) {
            abort(403);
        }
        $blocked = BlockedTask::with(['user', 'task'])
            ->where('course_id', $course->id)
            ->orderBy('user_id')
            ->orderBy('task_id')
            ->get()
            ->groupBy('user_id');

        return view('courses.blocked', compact('course', 'user', 'blocked'));
    }

    public function details($id, Request $request)
    {
    // Record action
    ActionLog::record(Auth::user()->id, 'course', $id);
    // Load current user with relations
    $user = Auth::user()->load('solutions', 'solutions.task');
        // Optimized: load only essential relations, defer heavy loading
        $course = Course::with([
            'program.chapters',
            'program.lessons' => function($query) {
                $query->orderBy('sort_index');
            },
            'students' => function($query) {
                $query->select('users.id', 'users.name', 'users.image');
            },
            'teachers'
        ])->findOrFail($id);

        // Use the already loaded students
        $students = $course->students;


        // Cache marks for this course
        $marks = Cache::remember("course:{$id}:marks", 5, function () use ($id) {
            return CompletedCourse::where('course_id', $id)->get();
        });

        //Made this due to some issues on my local server
        $cstudent = [];
        if ($request->has('chapter')) {
            $chapter = $course->program->chapters->where('id', $request->chapter)->first();
            if (!$chapter) {
                abort(404);
            }
        } else {
            if ($user->role == 'admin' || $course->teachers->contains($user)) {
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

        // Now load steps and tasks for lessons in the current chapter
        $course->load([
            'program.lessons.steps.tasks',
            'program.lessons.info'
        ]);

        $temp_steps = collect([]);

        $lessons = $course->program->lessons->filter(function ($lesson) use ($course, $chapter) {
            return $lesson->isStarted($course) && $lesson->chapter_id == $chapter->id;
        });

        foreach ($lessons as $lesson) {
            $temp_steps = $temp_steps->merge($lesson->steps);
        }

        // We don't need all_steps anymore - we use cached points from database

        $this->applyCachedCoursePoints($id, $students);


        if ($course->students->contains($user)) {
            // Keep the order
            $lessons = $course->program->lessons->where('chapter_id', $chapter->id)->sortBy('sort_index')->values();

            $steps = $temp_steps;
            $cstudent = $students->filter(function ($value, $key) use ($user) {
                return $value->id == $user->id;
            })->first();
        } else {
            $steps = $temp_steps;
            // For teachers, show all lessons in this chapter
            $lessons = $course->program->lessons->where('chapter_id', $chapter->id)->sortBy('sort_index')->values();
        }

        // Preload lesson statistics for all students and lessons in this chapter
        $lessonStats = LessonStudentStats::where('course_id', $id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('lesson_id')
            ->map(function ($stats) {
                return $stats->keyBy('student_id');
            });

        // Render view without caching for now (caching causes stale data issues)
        return view('courses.details', compact('chapter', 'course', 'user', 'steps', 'students', 'cstudent', 'lessons', 'marks', 'lessonStats'));
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

    public function editChapter($course_id, $chapter_id, UpdateChapterRequest $request)
    {
        $chapter = ProgramChapter::findOrFail($chapter_id);

        $chapter->name = $request->name;
        $chapter->description = clean($request->description);
        $chapter->save();

        return redirect('/insider/courses/' . $course_id . '?chapter=' . $chapter_id);
    }

    public function createChapter($course_id, CreateChapterRequest $request)
    {
        $course = Course::findOrFail($course_id);
        $program = $course->program;

        $order = $this->getNextChapterSortIndex($program);

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

    public function edit($id, UpdateCourseRequest $request)
    {
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
                    $this->promoteNoviceToStudent($user);
                }
            }


        if ($course->invite != $request->invite) {
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

    public function create(StoreCourseRequest $request)
    {
        $user = Auth::user();

        $course = new Course();
        if ($request->program >= 0) {
            $request->validate(['program' => 'required|integer|exists:programs,id']);
            $course->program_id = $request->program;
        } elseif ($request->program == -1) {
            $program = new Program();
            $program->name = $request->name;
            $program->save();

            $course->program_id = $program->id;

            $order = $this->getNextChapterSortIndex($program);

            $chapter = new ProgramChapter();
            $chapter->name = $request->name;
            $chapter->program_id = $program->id;
            $chapter->sort_index = $order;
            $chapter->save();
        } else {
            abort(422);
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


    public function invite(InviteCourseRequest $request)
    {
        $user = Auth::user();
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
        $this->promoteNoviceToStudent($user);

        $this->make_success_alert('Успех!', 'Вы присоединились к курсу "' . $course->name . '".');
        $course->students()->attach([$user->id => ['is_remote' => $remote]]);


        return redirect()->back();
    }

    public function enroll($id, Request $request)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);

        if ($user->role == 'admin' || $course->teachers->contains($user)) return redirect('/insider/courses/' . $course->id);

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

    public function exportMarkdown($id)
    {
        $course = Course::findOrFail($id);
        $chapters = $course->program->chapters()->with('lessons.steps.tasks')->get();

        $tempDir = sys_get_temp_dir() . '/course-' . $id . '-' . time();
        mkdir($tempDir);

        foreach ($chapters as $chapterIndex => $chapter) {
            $chapterNumber = $chapterIndex + 1;
            $chapterDirName = sprintf('%02d-%s', $chapterNumber, $this->sanitizeFileName($chapter->name) ?: 'chapter-' . $chapter->id);
            $chapterDir = $tempDir . '/' . $chapterDirName;
            mkdir($chapterDir);

            foreach ($chapter->lessons as $lessonIndex => $lesson) {
                $lessonNumber = $lessonIndex + 1;
                $lessonDirName = sprintf('%02d-%s', $lessonNumber, $this->sanitizeFileName($lesson->name) ?: 'lesson-' . $lesson->id);
                $lessonDir = $chapterDir . '/' . $lessonDirName;
                mkdir($lessonDir);

                foreach ($lesson->steps as $stepIndex => $step) {
                    $stepNumber = $stepIndex + 1;

                    if ($step->is_notebook && !empty($step->theory)) {
                        $fileName = sprintf('%02d-%s.ipynb', $stepNumber, $this->sanitizeFileName($step->name));
                        file_put_contents($lessonDir . '/' . $fileName, $step->theory);
                    } else {
                        $fileName = sprintf('%02d-%s.md', $stepNumber, $this->sanitizeFileName($step->name));
                        $content = "# {$step->name}\n\n";

                        if (!empty($step->theory)) {
                            $content .= "## Теория\n\n{$step->theory}\n\n";
                        }
                        if (!empty($step->notes)) {
                            $content .= "## Заметки\n\n{$step->notes}\n\n";
                        }
                        if ($step->tasks->count() > 0) {
                            $content .= "## Задачи\n\n";
                            foreach ($step->tasks as $taskIndex => $task) {
                                $content .= "### Задача " . ($taskIndex + 1) . ": {$task->name}\n\n";
                                if (!empty($task->text)) {
                                    $content .= "{$task->text}\n\n";
                                }
                                $metadata = [];
                                if ($task->max_mark > 0) $metadata[] = "**Максимальный балл:** {$task->max_mark}";
                                if ($task->is_star) $metadata[] = "**Звёздочка:** Да";
                                if ($task->answer) $metadata[] = "**Правильный ответ:** {$task->answer}";
                                if (!empty($metadata)) $content .= implode(" | ", $metadata) . "\n\n";
                                $content .= "---\n\n";
                            }
                        }
                        file_put_contents($lessonDir . '/' . $fileName, $content);
                    }
                }
            }
        }

        $zipPath = sys_get_temp_dir() . '/course-' . $id . '-' . time() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addDirToZip($zip, $tempDir, '');
            $zip->close();
        }

        $this->deleteDirectory($tempDir);

        $safeName = $this->sanitizeFileName($course->name) ?: 'course-' . $id;

        $response = \Response::make(file_get_contents($zipPath));
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="' . $safeName . '.zip"');
        register_shutdown_function('unlink', $zipPath);

        return $response;
    }

    private function applyCachedCoursePoints($courseId, $students)
    {
        $cachedPoints = CourseStudentPoints::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        foreach ($students as $key => $student) {
            if (isset($cachedPoints[$student->id])) {
                $students[$key]->percent = $cachedPoints[$student->id]->percent;
                $students[$key]->max_points = $cachedPoints[$student->id]->max_points;
                $students[$key]->points = $cachedPoints[$student->id]->points;
                continue;
            }

            $this->progressService->recalculateCoursePoints($courseId, $student->id);
            $students[$key]->percent = 0;
            $students[$key]->max_points = 0;
            $students[$key]->points = 0;
        }
    }

    private function getNextChapterSortIndex($program)
    {
        if ($program->chapters->count() == 0) {
            return 100;
        }

        return $program->chapters->last()->sort_index + 1;
    }

    private function promoteNoviceToStudent($user)
    {
        if ($user->role != 'novice') {
            return;
        }

        $user->role = 'student';
        $user->save();
    }

    private function addDirToZip(\ZipArchive $zip, $dir, $prefix)
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            $localName = $prefix ? $prefix . '/' . $this->transliterate($item) : $this->transliterate($item);
            if (is_dir($path)) {
                $zip->addEmptyDir($localName);
                $this->addDirToZip($zip, $path, $localName);
            } else {
                $zip->addFile($path, $localName);
            }
        }
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item);
        }
        return rmdir($dir);
    }

    private function sanitizeFileName($name)
    {
        $name = $this->transliterate($name);
        $name = preg_replace('/[^a-zA-Z0-9\s-]/', '', $name);
        $name = preg_replace('/\s+/', '-', $name);
        $name = trim($name, '-');
        return mb_substr($name, 0, 50);
    }

    private function transliterate($text)
    {
        $converter = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];
        return strtr($text, $converter);
    }
}
