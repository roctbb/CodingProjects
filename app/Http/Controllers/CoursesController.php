<?php

namespace App\Http\Controllers;

use App\ActionLog;
use App\CompletedCourse;
use App\Course;
use App\CourseActivity;
use App\CourseCategory;
use App\CourseStudentPoints;
use App\Jobs\RecalculateCoursePoints;
use App\LessonStudentStats;
use App\Program;
use App\ProgramChapter;
use App\Solution;
use App\TaskDeadline;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\User;
use App\BlockedTask;
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
        $this->middleware('course')->only(['details', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'createChapter', 'editChapter', 'setDefaultChapter', 'createChapterView', 'editChapterView', 'exportMarkdown']);
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'start', 'stop', 'edit', 'assessments', 'report', 'reviews', 'resetPendingReviews', 'createChapter', 'editChapter', 'setDefaultChapter', 'createChapterView', 'editChapterView', 'exportMarkdown']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();

        $isTeacher = $user->role == 'teacher' || $user->role == 'admin';
        $courseColumns = ['id', 'program_id', 'name', 'description', 'state', 'mode', 'level', 'start_date', 'end_date'];
        $courseRelations = [
            'teachers:id,name,image,avatar_frame,avatar_frame_expires_at',
        ];

        $startedCoursesQuery = Course::query()
            ->select($courseColumns)
            ->with($courseRelations)
            ->withCount('lessons')
            ->where('state', 'started')
            ->orderBy('id');

        if ($user->role == 'admin') {
            $my_courses = (clone $startedCoursesQuery)->get();
        } else {
            $my_courses = (clone $startedCoursesQuery)
                ->where(function ($query) use ($user) {
                    $query->whereHas('students', function ($query) use ($user) {
                        $query->where('users.id', $user->id);
                    })->orWhereHas('teachers', function ($query) use ($user) {
                        $query->where('users.id', $user->id);
                    });
                })
                ->get();
        }

        $open_courses = collect();
        $private_courses = collect();
        $availableCourses = collect();

        if (!$isTeacher) {
            $availableCoursesQuery = (clone $startedCoursesQuery)
                ->whereDoesntHave('students', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->whereDoesntHave('teachers', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                });

            $open_courses = (clone $availableCoursesQuery)->where('mode', 'open')->get();
            $private_courses = (clone $availableCoursesQuery)
                ->where(function ($query) {
                    $query->where('mode', '!=', 'open')->orWhereNull('mode');
                })
                ->get();
            $availableCourses = $open_courses->merge($private_courses);
        }

        $draftCourses = collect();
        $archiveCourses = collect();

        if ($isTeacher) {
            $managedCoursesQuery = Course::query()
                ->select($courseColumns)
                ->with($courseRelations)
                ->withCount('lessons')
                ->when($user->role != 'admin', function ($query) use ($user) {
                    $query->whereHas('teachers', function ($query) use ($user) {
                        $query->where('users.id', $user->id);
                    });
                });

            $draftCourses = (clone $managedCoursesQuery)
                ->where('state', 'draft')
                ->orderBy('id')
                ->get();

            $archiveCourses = (clone $managedCoursesQuery)
                ->where('state', 'ended')
                ->orderByDesc('start_date')
                ->get();
        }

        $courses = $my_courses
            ->merge($open_courses)
            ->merge($private_courses)
            ->merge($draftCourses)
            ->merge($archiveCourses)
            ->unique('id')
            ->values();
        $activeCourses = $my_courses;

        $activeCourseIds = $my_courses->pluck('id');
        $courseProgress = CourseStudentPoints::where('student_id', $user->id)
            ->whereIn('course_id', $activeCourseIds)
            ->get()
            ->keyBy('course_id');

        $upcomingDeadlines = collect([]);
        $pendingSolutionsTotal = 0;
        $pulse = CourseActivity::pulseForCourses($activeCourseIds);

        if (!($user->role == 'teacher' || $user->role == 'admin')) {
            $submittedTaskIds = Solution::where('user_id', $user->id)
                ->whereIn('course_id', $activeCourseIds)
                ->whereNotNull('mark')
                ->pluck('task_id');

            $upcomingDeadlines = TaskDeadline::with(['task:id,name,is_hidden', 'course:id,name'])
                ->whereIn('course_id', $activeCourseIds)
                ->whereDate('expiration', '>=', Carbon::today())
                ->whereHas('task', function ($query) {
                    $query->where('is_hidden', false);
                })
                ->whereNotIn('task_id', $submittedTaskIds)
                ->orderBy('expiration')
                ->take(5)
                ->get();
        }

        if ($user->role == 'teacher' || $user->role == 'admin') {
            $managedActiveCourseIds = $user->role == 'admin'
                ? $activeCourseIds
                : $my_courses->filter(function ($course) use ($user) {
                    return $course->teachers->contains($user);
                })->pluck('id');

            $pendingSolutionsQuery = Solution::select(['id', 'course_id', 'task_id', 'user_id', 'submitted'])
                ->with(['course:id,name', 'task:id,name', 'user:id,name,image,custom_title,custom_title_expires_at,avatar_frame,avatar_frame_expires_at'])
                ->whereIn('course_id', $managedActiveCourseIds)
                ->pendingReview()
                ->orderByDesc('submitted')
                ->orderByDesc('id');

            $pendingSolutionsTotal = (clone $pendingSolutionsQuery)->count();
        }

        $notifications = collect([]);
        foreach ($user->unreadNotifications as $notification) {
            $notifications->push($notification);
            $notification->markAsRead();
        }

        $today = Carbon::now()->startOfDay();
        $birthdayUsers = Cache::remember('users:nearby_birthdays:' . $today->format('Y-m-d'), 3600, function () use ($today) {
            return User::where('is_hidden', false)
                ->whereNotNull('birthday')
                ->get(['id', 'name', 'birthday', 'custom_title', 'custom_title_expires_at'])
                ->map(function ($birthdayUser) use ($today) {
                    $nextBirthday = $birthdayUser->birthday->copy()->year($today->year)->startOfDay();

                    if ($nextBirthday->lt($today)) {
                        $nextBirthday->addYear();
                    }

                    $birthdayUser->setAttribute('next_birthday_at', $nextBirthday);
                    $birthdayUser->setAttribute('birthday_distance_days', $today->diffInDays($nextBirthday));

                    return $birthdayUser;
                })->filter(function ($birthdayUser) {
                    return $birthdayUser->birthday_distance_days <= 20;
                })->sortBy('birthday_distance_days')->values();
        });

        return response()->view('home', compact('courses', 'user', 'my_courses', 'open_courses', 'private_courses', 'availableCourses', 'activeCourses', 'draftCourses', 'archiveCourses', 'birthdayUsers', 'notifications', 'courseProgress', 'upcomingDeadlines', 'pendingSolutionsTotal', 'pulse', 'isTeacher'));
    }

    public function pulse()
    {
        $user = Auth::user();
        $courses = Course::with(['students:id', 'teachers:id'])->orderBy('id')->get();

        $activeCourseIds = $courses->filter(function ($course) use ($user) {
            return $course->state == 'started'
                && ($user->role == 'admin'
                    || $course->students->contains('id', $user->id)
                    || $course->teachers->contains('id', $user->id));
        })->pluck('id');

        $activities = CourseActivity::with(['user', 'course', 'lesson', 'task'])
            ->whereIn('course_id', $activeCourseIds)
            ->orderBy('created_at', 'desc')
            ->paginate(40);
        $pulse = CourseActivity::pulseForCourses($activeCourseIds);

        return view('courses.pulse', compact('user', 'activities', 'pulse'));
    }

    public function reviews()
    {
        $user = Auth::user();
        $managedActiveCourseIds = Course::query()
            ->where('state', 'started')
            ->when($user->role != 'admin', function ($query) use ($user) {
                $query->whereHas('teachers', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                });
            })
            ->pluck('id');

        $pendingSolutions = Solution::select(['id', 'course_id', 'task_id', 'user_id', 'submitted'])
            ->with([
                'course:id,name',
                'task:id,name',
                'user:id,name,image,custom_title,custom_title_expires_at,avatar_frame,avatar_frame_expires_at',
            ])
            ->whereIn('course_id', $managedActiveCourseIds)
            ->pendingReview()
            ->orderByDesc('submitted')
            ->orderByDesc('id')
            ->paginate(40);

        return view('courses.reviews', compact('user', 'pendingSolutions'));
    }

    public function resetPendingReviews()
    {
        $user = Auth::user();
        $managedActiveCourseIds = Course::query()
            ->where('state', 'started')
            ->when($user->role != 'admin', function ($query) use ($user) {
                $query->whereHas('teachers', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                });
            })
            ->pluck('id');

        $pendingSolutionIds = Solution::whereIn('course_id', $managedActiveCourseIds)
            ->pendingReview()
            ->pluck('id');

        $updated = 0;
        if ($pendingSolutionIds->isNotEmpty()) {
            $updated = Solution::whereIn('id', $pendingSolutionIds)->update([
                'review_skipped' => true,
                'recheck_requested' => false,
            ]);
        }

        $this->make_success_alert('Очередь очищена', 'Пропущено непроверенных решений: ' . $updated . '.');

        return redirect()->back();
    }

    public function report($id)
    {
        $user = User::with('solutions', 'solutions.task')->findOrFail(Auth::User()->id);
        $course = Course::with([
            'program.lessons.info',
            'program.lessons.steps.tasks.solutions' => function ($query) use ($id) {
                $query->where('course_id', $id);
            },
            'program.lessons.steps.tasks.blockedTasks' => function ($query) use ($id) {
                $query->where('course_id', $id);
            },
            'students',
            'students.submissions',
            'teachers',
        ])->findOrFail($id);
        $students = $course->students;

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
            $twoWeeksAgo = Carbon::now()->addWeeks(-2);


            $use_records = ActionLog::where('created_at', '>', $twoWeeksAgo)
                ->whereIn('user_id', $ids)
                ->where(function ($query) use ($steps_ids, $course) {
                    $query->where(function ($query) use ($course) {
                        $query->where('type', 'course')->where('object_id', $course->id);
                    })->orWhere(function ($query) use ($steps_ids) {
                        $query->where('type', 'step')->whereIn('object_id', $steps_ids);
                    });
                })
                ->get()
                ->groupBy('user_id')
                ->map(function ($item) {
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

                $pulse_keys[$student_id] = $use_records[$student_id]->keys()->values()->toJson();
                $pulse_values[$student_id] = $use_records[$student_id]->values()->toJson();
            }

            $task_records = Solution::where('course_id', $course->id)
                ->where('created_at', '>', $twoWeeksAgo)
                ->whereIn('user_id', $ids)
                ->get()
                ->groupBy('user_id')
                ->map(function ($item) {
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

                $task_keys[$student_id] = $task_records[$student_id]->keys()->values()->toJson();
                $task_values[$student_id] = $task_records[$student_id]->values()->toJson();
            }


            // Get cached points from database
            $cachedPoints = CourseStudentPoints::where('course_id', $id)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');

            $studentsNeedingRecalculate = collect();

            foreach ($students as $key => $value) {
                if (isset($cachedPoints[$value->id])) {
                    $students[$key]->percent = $cachedPoints[$value->id]->percent;
                    $students[$key]->max_points = $cachedPoints[$value->id]->max_points;
                    $students[$key]->points = $cachedPoints[$value->id]->points;
                } else {
                    $studentsNeedingRecalculate->push($value->id);
                    $students[$key]->percent = 0;
                    $students[$key]->max_points = 0;
                    $students[$key]->points = 0;
                }
            }

            $lessonStats = LessonStudentStats::where('course_id', $id)
                ->whereIn('lesson_id', $lessons->pluck('id'))
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('lesson_id')
                ->map(function ($stats) {
                    return $stats->keyBy('student_id');
                });

            foreach ($lessons as $lesson) {
                if (!isset($lessonStats[$lesson->id])) {
                    $lessonStats[$lesson->id] = collect();
                }

                foreach ($students as $student) {
                    if (!isset($lessonStats[$lesson->id][$student->id])) {
                        $studentsNeedingRecalculate->push($student->id);
                        $lessonStats[$lesson->id]->put($student->id, new LessonStudentStats([
                            'course_id' => $id,
                            'lesson_id' => $lesson->id,
                            'student_id' => $student->id,
                            'points' => 0,
                            'max_points' => 0,
                            'percent' => 0,
                        ]));
                    }
                }
            }

            if ($studentsNeedingRecalculate->isNotEmpty()) {
                RecalculateCoursePoints::dispatch($id, $studentsNeedingRecalculate->unique()->values()->all());
            }

            $steps = $temp_steps;

        return view('courses.report', compact('course', 'user', 'steps', 'students', 'lessons', 'pulse_keys', 'pulse_values', 'task_keys', 'task_values', 'lessonStats'));

    }

    public function blocked($id)
    {
        $user = User::findOrFail(Auth::User()->id);
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
            'program.lessons.earlyAccesses' => function ($query) use ($id, $user) {
                $query->where('course_id', $id)->where('user_id', $user->id);
            },
            'students' => function($query) {
                $query->select('users.id', 'users.name', 'users.image', 'users.custom_title', 'users.custom_title_expires_at', 'users.avatar_frame', 'users.avatar_frame_expires_at');
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
            } elseif ($course->default_chapter_id != null && $course->program->chapters->contains('id', $course->default_chapter_id)) {
                $chapter = $course->program->chapters->where('id', $course->default_chapter_id)->first();
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
                'program.lessons.steps.tasks.deadlines' => function ($query) use ($id) {
                    $query->where('course_id', $id);
                },
                'program.lessons.steps.tasks.solutions' => function ($query) use ($id) {
                    $query->where('course_id', $id);
                },
                'program.lessons.info',
                'program.lessons.earlyAccesses' => function ($query) use ($id, $user) {
                    $query->where('course_id', $id)->where('user_id', $user->id);
                },
            ]);

            $temp_steps = collect([]);

            $lessons = $course->program->lessons->filter(function ($lesson) use ($course, $chapter, $user) {
                return $lesson->chapter_id == $chapter->id && $lesson->isAvailableForUser($course, $user);
            });

            foreach ($lessons as $lesson) {
                $temp_steps = $temp_steps->merge($lesson->steps);
            }

            // We don't need all_steps anymore - we use cached points from database

            // Get cached points from database
            $cachedPoints = CourseStudentPoints::where('course_id', $id)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');

            // Assign cached percents and points to students
            $studentsNeedingRecalculate = collect();

            foreach ($students as $key => $student) {
                if (isset($cachedPoints[$student->id])) {
                    $students[$key]->percent = $cachedPoints[$student->id]->percent;
                    $students[$key]->points = $cachedPoints[$student->id]->points;
                    $students[$key]->max_points = $cachedPoints[$student->id]->max_points;
                } else {
                    $studentsNeedingRecalculate->push($student->id);
                    $students[$key]->percent = 0;
                    $students[$key]->points = 0;
                    $students[$key]->max_points = 0;
                }
            }

            if ($studentsNeedingRecalculate->isNotEmpty()) {
                RecalculateCoursePoints::dispatch($id, $studentsNeedingRecalculate->unique()->values()->all());
            }


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

            $isManager = $user->role == 'admin' || $course->teachers->contains($user);
            $chapterTaskIds = $steps->flatMap(function ($step) {
                return $step->tasks->pluck('id');
            })->values();

            $courseDeadlines = TaskDeadline::with(['task.step.lesson'])
                ->where('course_id', $id)
                ->whereIn('task_id', $chapterTaskIds)
                ->orderBy('expiration')
                ->get()
                ->filter(function ($deadline) use ($course, $user, $isManager) {
                    if (!$deadline->task) {
                        return false;
                    }

                    if ($isManager) {
                        return true;
                    }

                    return $deadline->task->isVisible($user, $course) && !$deadline->task->isDone($user->id);
                })
                ->map(function ($deadline) {
                    $expiration = $deadline->expiration->copy();
                    $deadline->is_overdue = $expiration->copy()->addDay()->lt(Carbon::now());
                    $deadline->is_soon = !$deadline->is_overdue && Carbon::now()->addDays(3)->gt($expiration);

                    return $deadline;
                })
                ->sortBy(function ($deadline) {
                    return ($deadline->is_overdue ? 0 : 1) . '-' . $deadline->expiration->timestamp;
                })
                ->values();

            // Preload lesson statistics for all students and lessons in this chapter
            $lessonStats = LessonStudentStats::where('course_id', $id)
                ->whereIn('lesson_id', $lessons->pluck('id'))
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('lesson_id')
                ->map(function ($stats) {
                    return $stats->keyBy('student_id');
                });

            $allLessonStats = LessonStudentStats::where('course_id', $id)
                ->whereIn('lesson_id', $course->program->lessons->pluck('id'))
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('lesson_id')
                ->map(function ($stats) {
                    return $stats->keyBy('student_id');
                });

            $chapterProgress = collect();
            foreach ($course->program->chapters as $programChapter) {
                $chapterLessonIds = $course->program->lessons
                    ->where('chapter_id', $programChapter->id)
                    ->pluck('id');

                if ($isManager) {
                    $doneStudents = 0;

                    foreach ($students as $student) {
                        $points = 0;
                        $maxPoints = 0;

                        foreach ($chapterLessonIds as $lessonId) {
                            $stat = $allLessonStats[$lessonId][$student->id] ?? null;
                            $points += $stat ? (int) $stat->points : 0;
                            $maxPoints += $stat ? (int) $stat->max_points : 0;
                        }

                        if ($maxPoints > 0 && $points >= $maxPoints) {
                            $doneStudents++;
                        }
                    }

                    $chapterProgress->put($programChapter->id, $students->count() ? $doneStudents * 100 / $students->count() : 0);
                    continue;
                }

                $points = 0;
                $maxPoints = 0;

                foreach ($chapterLessonIds as $lessonId) {
                    $stat = isset($cstudent) ? ($allLessonStats[$lessonId][$cstudent->id] ?? null) : null;
                    $points += $stat ? (int) $stat->points : 0;
                    $maxPoints += $stat ? (int) $stat->max_points : 0;
                }

                $chapterProgress->put($programChapter->id, $maxPoints > 0 ? min(100, $points * 100 / $maxPoints) : 0);
            }

            // Render view without caching for now (caching causes stale data issues)
        return view('courses.details', compact('chapter', 'course', 'user', 'steps', 'students', 'cstudent', 'lessons', 'marks', 'lessonStats', 'courseDeadlines', 'chapterProgress'));


    }

    public function assessments($id)
    {
        $course = Course::with([
            'program.lessons.steps.tasks',
            'students.submissions',
        ])->findOrFail($id);

        $blockedTaskMap = BlockedTask::where('course_id', $course->id)
            ->get(['user_id', 'task_id'])
            ->mapWithKeys(function ($item) {
                return [$item->user_id . ':' . $item->task_id => true];
            });

        return view('courses.assessments', compact('course', 'blockedTaskMap'));
    }

    public function createView()
    {
        $programs = Program::all();
        return view('courses.create', compact('programs'));
    }

    public function editView($id)
    {
        $course = Course::with(['categories', 'teachers', 'students'])->findOrFail($id);
        $categories = CourseCategory::orderBy('title')->get(['id', 'title']);
        $teachers = User::where('role', 'teacher')
            ->orWhere('role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name']);
        $students = User::orderBy('name')->get(['id', 'name']);

        return view('courses.edit', compact('course', 'categories', 'teachers', 'students'));
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

    public function setDefaultChapter($course_id, $chapter_id, Request $request)
    {
        $course = Course::with('program.chapters')->findOrFail($course_id);
        $chapter = $course->program->chapters->where('id', $chapter_id)->first();

        if (!$chapter) {
            abort(404);
        }

        $course->default_chapter_id = $chapter->id;
        $course->save();

        return redirect('/insider/courses/' . $course_id . '?chapter=' . $chapter_id)
            ->with('alert-class', 'alert-success')
            ->with('alert-destination', 'head')
            ->with('alert-title', 'Глава обновлена')
            ->with('alert-text', 'Теперь эта глава открывается по умолчанию.');
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
