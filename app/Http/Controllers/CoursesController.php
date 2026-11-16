<?php

namespace App\Http\Controllers;

use App\ActionLog;
use App\Course;
use App\CourseActivity;
use App\CourseCategory;
use App\CourseStudentPoints;
use App\Jobs\RecalculateCoursePoints;
use App\LessonStudentStats;
use App\Program;
use App\ProgramChapter;
use App\Solution;
use App\Services\CoursePosterGenerator;
use App\Queries\CourseDeadlineList;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\User;
use App\BlockedTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $this->middleware('course')->only(['details', 'deadlines', 'editView', 'start', 'stop', 'edit', 'generatePoster', 'assessments', 'report', 'resetStudentGeekPasteWarning', 'createChapter', 'editChapter', 'setDefaultChapter', 'createChapterView', 'editChapterView', 'exportMarkdown']);
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'start', 'stop', 'edit', 'generatePoster', 'assessments', 'report', 'resetStudentGeekPasteWarning', 'reviews', 'resetPendingReviews', 'createChapter', 'editChapter', 'setDefaultChapter', 'createChapterView', 'editChapterView', 'exportMarkdown']);
        $this->middleware(['course', 'teacher'])->only('exportChapterPoints');
        $this->middleware('course')->only(['setCurrentLesson', 'lessonStatistics']);
        $this->middleware('course.manage')->only(['editView', 'start', 'stop', 'edit', 'generatePoster', 'assessments', 'report',
            'resetStudentGeekPasteWarning', 'createChapter', 'createChapterView', 'export', 'exportMarkdown', 'exportChapterPoints']);
        $this->middleware('course.manage:chapter')->only(['editChapter', 'editChapterView', 'setDefaultChapter', 'makeChapterLower', 'makeChapterUpper']);
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
        if (Schema::hasColumn('courses', 'icon_key')) {
            $courseColumns[] = 'icon_key';
        }
        if (Schema::hasColumn('courses', 'current_lesson_id')) {
            $courseColumns[] = 'current_lesson_id';
        }
        $courseRelations = [
            'teachers:id,name,image,avatar_frame,avatar_frame_config,avatar_frame_expires_at',
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
        $availableCourses = collect();
        $completedCourses = collect();

        if (!$isTeacher) {
            $availableCoursesQuery = (clone $startedCoursesQuery)
                ->whereDoesntHave('students', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->whereDoesntHave('teachers', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                });

            $open_courses = (clone $availableCoursesQuery)->where('mode', 'open')->get();
            $availableCourses = $open_courses;
            $completedCourses = app(\App\Queries\StudentCompletedCourses::class)->forStudent($user);
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
        $pulse = app(\App\Services\CoursePulse::class)->forCourses($activeCourseIds);

        if (!($user->role == 'teacher' || $user->role == 'admin')) {
            $upcomingDeadlines = app(CourseDeadlineList::class)->forCourses($my_courses, $user, upcomingOnly: true)->take(5);

        }

        if ($user->role == 'teacher' || $user->role == 'admin') {
            $managedActiveCourseIds = $user->role == 'admin'
                ? $activeCourseIds
                : $my_courses->filter(function ($course) use ($user) {
                    return $course->teachers->contains($user);
                })->pluck('id');

            $pendingSolutionsQuery = Solution::select(['id', 'course_id', 'task_id', 'user_id', 'submitted'])
                ->with(['course:id,name', 'task:id,name', 'user:id,name,image,custom_title,custom_title_expires_at,avatar_frame,avatar_frame_config,avatar_frame_expires_at'])
                ->whereIn('course_id', $managedActiveCourseIds)
                ->pendingReview()
                ->orderByDesc('submitted')
                ->orderByDesc('id');

            $pendingSolutionsTotal = (clone $pendingSolutionsQuery)->count();
        }

        $notifications = collect([]);
        $unreadNotifications = $user->unreadNotifications;
        $coinNoticeIds = collect();
        if ($user->role === 'student' && Schema::hasTable('reward_notices')) {
            $sources = $unreadNotifications->pluck('data.coin_transaction_id')->filter()->map(fn ($id) => (string) $id);
            if ($sources->isNotEmpty()) {
                $coinNoticeIds = \DB::table('reward_notices')->where('user_id', $user->id)->where('kind', 'coins')
                    ->whereIn('source_key', $sources)->pluck('source_key');
            }
        }
        foreach ($unreadNotifications as $notification) {
            $shownAsReward = $notification->type === \App\Notifications\NewCoinTransaction::class
                && $coinNoticeIds->contains((string) ($notification->data['coin_transaction_id'] ?? ''));
            if (!$shownAsReward) $notifications->push($notification);
            $notification->markAsRead();
        }

        $today = Carbon::now()->startOfDay();
        $birthdayUsers = Cache::remember(User::nearbyBirthdaysCacheKey($today), 3600, function () use ($today) {
            return User::where('is_hidden', false)
                ->withVisibleBirthday()
                ->get(['id', 'name', 'birthday', 'image', 'avatar_frame', 'avatar_frame_config', 'avatar_frame_expires_at'])
                ->map(function ($birthdayUser) use ($today) {
                    $nextBirthday = $birthdayUser->birthday->copy()->year($today->year)->startOfDay();

                    if ($nextBirthday->lt($today)) {
                        $nextBirthday->addYear();
                    }

                    $birthdayUser->setAttribute('next_birthday_at', $nextBirthday);
                    $birthdayUser->setAttribute('birthday_distance_days', $today->diffInDays($nextBirthday));

                    return $birthdayUser;
                })->filter(function ($birthdayUser) {
                    return $birthdayUser->birthday_distance_days < 7;
                })->sortBy(fn ($birthdayUser) => [$birthdayUser->birthday_distance_days, $birthdayUser->name])->values();
        });

        $projectJourney = app(\App\Services\HomeProjectJourney::class)->forStudent($activeCourses, $user);

        return response()->view('home', compact('courses', 'user', 'my_courses', 'open_courses', 'availableCourses', 'completedCourses', 'activeCourses', 'draftCourses', 'archiveCourses', 'birthdayUsers', 'notifications', 'courseProgress', 'upcomingDeadlines', 'pendingSolutionsTotal', 'pulse', 'isTeacher', 'projectJourney'));
    }

    public function pulse(Request $request)
    {
        $user = Auth::user();
        $courses = Course::with(['students:id', 'teachers:id'])->orderBy('id')->get();

        $activeCourseIds = $courses->filter(function ($course) use ($user) {
            return $course->state == 'started'
                && ($user->role == 'admin'
                    || $course->students->contains('id', $user->id)
                    || $course->teachers->contains('id', $user->id));
        })->pluck('id');

        $pulseCourses = $courses->whereIn('id', $activeCourseIds)->values();
        $activityGroups = [
            'learning' => ['label' => 'Учёба', 'types' => [CourseActivity::TYPE_SOLUTION_SUBMITTED, CourseActivity::TYPE_SOLUTION_CHECKED, CourseActivity::TYPE_TASK_CREATED, CourseActivity::TYPE_LESSON_CREATED, CourseActivity::TYPE_LESSON_OPENED, CourseActivity::TYPE_TASK_STRUGGLE, CourseActivity::TYPE_TASK_AI_SUMMARY, CourseActivity::TYPE_COURSE_DAILY_SUMMARY]],
            'rewards' => ['label' => 'Достижения', 'types' => [CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED, CourseActivity::TYPE_LESSON_COMPLETED, CourseActivity::TYPE_CHAPTER_COMPLETED, CourseActivity::TYPE_LEARNING_STREAK, CourseActivity::TYPE_XP_MILESTONE, CourseActivity::TYPE_FIRST_SOLUTION]],
            'store' => ['label' => 'Магазин', 'types' => [CourseActivity::TYPE_MARKET_PURCHASE, CourseActivity::TYPE_MARKET_AUCTION_WON, CourseActivity::TYPE_MARKET_DIGITAL_PURCHASE, CourseActivity::TYPE_AUCTION_LEADING_BID, CourseActivity::TYPE_AUCTION_FINISHED, CourseActivity::TYPE_MARKET_ORDER_SHIPPED]],
        ];
        $filters = $request->validate(['course' => 'nullable|integer|min:1', 'type' => 'nullable|in:learning,rewards,store', 'q' => 'nullable|string|max:200']);
        $pulseSearch = trim($filters['q'] ?? '');
        $selectedPulseCourse = $pulseCourses->firstWhere('id', $request->integer('course'));
        $activities = CourseActivity::with(['user', 'course', 'lesson', 'task', 'achievement'])
            ->where(function ($query) use ($activeCourseIds) {
                if ($activeCourseIds->isNotEmpty()) {
                    $query->whereIn('course_id', $activeCourseIds)
                        ->orWhere(function ($query) {
                            $query->whereNull('course_id')
                                ->whereIn('type', CourseActivity::globalPulseTypes());
                        });
                } else {
                    $query->whereNull('course_id')
                        ->whereIn('type', CourseActivity::globalPulseTypes());
                }
            })
            ->when($request->filled('course'), fn ($query) => $query->where('course_id', $request->integer('course')))
            ->when(isset($activityGroups[$request->input('type', '')]), fn ($query) => $query->whereIn('type', $activityGroups[$request->input('type')]['types']))
            ->when($pulseSearch !== '', function ($query) use ($pulseSearch) {
                $pattern = \App\Support\SqlTextSearch::pattern($pulseSearch);
                $name = \App\Support\SqlTextSearch::lower('name');
                $query->where(fn ($query) => $query->whereHas('user', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('course', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('lesson', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('task', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->orderBy('created_at', 'desc')->orderByDesc('id')
            ->paginate(40)->withQueryString();
        $pulseCourseIds = $request->filled('course') ? $activeCourseIds->filter(fn ($id) => $id === $request->integer('course'))->values() : $activeCourseIds;
        $pulse = app(\App\Services\CoursePulse::class)->forCourses($pulseCourseIds);

        return view('courses.pulse', compact('user', 'activities', 'pulse', 'pulseCourses', 'activityGroups', 'pulseSearch', 'selectedPulseCourse'));
    }

    public function reviews(Request $request)
    {
        $user = Auth::user();
        $workspaceService = app(\App\Services\ReviewWorkspace::class);
        $reviewFilters = $workspaceService->filters($request);
        $reviewCourses = $workspaceService->courses($user);
        $pendingSolutions = $workspaceService->queue($user, $request->filled('course') ? $request->integer('course') : null, $reviewFilters)
            ->paginate(40)->withQueryString();
        if ($pendingSolutions->currentPage() > $pendingSolutions->lastPage()) {
            return redirect()->to($request->fullUrlWithQuery(['page' => $pendingSolutions->lastPage()]));
        }

        $reviewQueue=$pendingSolutions;$reviewQueueAll=!$request->filled('course');
        $reviewLessonName = !empty($reviewFilters['lesson']) ? $pendingSolutions->first()?->task?->step?->lesson?->name : null;
        return view('courses.reviews', compact('user', 'pendingSolutions', 'reviewQueue','reviewQueueAll','reviewFilters', 'reviewCourses', 'reviewLessonName'));
    }

    public function resetPendingReviews(Request $request)
    {
        $user = Auth::user();
        $workspace = app(\App\Services\ReviewWorkspace::class);
        $filters = $workspace->filters($request);
        $pendingSolutionIds = $workspace->queue($user, $request->filled('course') ? $request->integer('course') : null, $filters)->pluck('id');

        $updated = 0;
        if ($pendingSolutionIds->isNotEmpty()) {
            $updated = Solution::whereIn('id', $pendingSolutionIds)->update([
                'review_skipped' => true,
                'recheck_requested' => false,
                'recheck_comment' => null,
            ]);
        }

        $this->make_success_alert('Очередь очищена', 'Пропущено непроверенных решений: ' . $updated . '.');

        return redirect()->back();
    }

    public function report($id)
    {
        $user = User::with('solutions', 'solutions.task')->findOrFail(Auth::User()->id);
        $course = Course::with([
            'program.chapters',
            'program.lessons.info',
            'program.lessons.steps.tasks.solutions' => function ($query) use ($id) {
                $query->where('course_id', $id);
            },
            'program.lessons.steps.tasks.blockedTasks' => function ($query) use ($id) {
                $query->where('course_id', $id);
            },
            'students',
            'statsStudents.submissions' => function ($query) use ($id) {
                $query->where('course_id', $id)
                    ->select('id', 'task_id', 'course_id', 'user_id', 'mark', 'submitted', 'review_skipped', 'recheck_requested')->withEvaluation();
            },
            'teachers',
        ])->findOrFail($id);
        $students = $course->statsStudents;

            [$lessons, $reportChapterGroups] = \App\Support\CourseReportLessons::groups($course);

            $temp_steps = collect([]);
            foreach ($course->program->lessons->filter(fn ($lesson) => $lesson->isStarted($course)) as $lesson) {
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
            $geekPasteIntegrityStats = $this->buildGeekPasteIntegrityReport($students, $lessons);

        $pendingReviewSolutions = $course->state === 'started'
            ? Solution::where('course_id', $course->id)->pendingReview()->get(['id', 'user_id', 'task_id']) : collect();
        $pendingSolutionsCount = $pendingReviewSolutions->count();
        $pendingReviewPairs = $pendingReviewSolutions->mapWithKeys(fn ($solution) => [$solution->user_id.':'.$solution->task_id => true]);
        if (request('attention') === '1') {
            $students = $students->filter(fn ($student) => ($student->max_points > 0 && $student->percent < 50)
                || in_array($geekPasteIntegrityStats[$student->id]['overall']['risk_level'] ?? 'none', ['high', 'medium'], true))->values();
        }
        return view('courses.report', compact('pendingReviewPairs', 'pendingSolutionsCount', 'course', 'user', 'steps', 'students', 'lessons', 'reportChapterGroups', 'pulse_keys', 'pulse_values', 'task_keys', 'task_values', 'lessonStats', 'geekPasteIntegrityStats'));

    }

    public function resetStudentGeekPasteWarning($id, $student_id)
    {
        $course = Course::with('students')->findOrFail($id);

        if (!$course->students->contains('id', (int) $student_id)) {
            abort(404);
        }

        $updateValues = [
            'geekpaste_ai_warning' => false,
            'geekpaste_ai_confidence' => null,
            'geekpaste_ai_reasons' => null,
            'geekpaste_llm_probability' => null,
            'geekpaste_similarity_warning' => false,
            'geekpaste_similarity_critical' => false,
            'geekpaste_similarity_max_percent' => null,
            'geekpaste_similarity_matches_count' => 0,
        ];

        if (Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_at')) {
            $updateValues['geekpaste_integrity_dismissed_at'] = Carbon::now();
        }

        if (Schema::hasColumn('solutions', 'geekpaste_integrity_dismissed_by')) {
            $updateValues['geekpaste_integrity_dismissed_by'] = Auth::id();
        }

        $updated = Solution::where('course_id', $course->id)
            ->where('user_id', (int) $student_id)
            ->where(function ($query) {
                $query->where('geekpaste_ai_warning', true)
                    ->orWhere('geekpaste_similarity_warning', true)
                    ->orWhere('geekpaste_similarity_critical', true)
                    ->orWhereNotNull('geekpaste_llm_probability')
                    ->orWhereNotNull('geekpaste_similarity_max_percent');
            })
            ->update($updateValues);

        if ($updated > 0) {
            $this->make_success_alert('Предупреждение снято', 'Сняты предупреждения анализа авторства для ученика.');
        } else {
            $this->make_info_alert('Без изменений', 'У ученика не было активных предупреждений анализа авторства.');
        }

        return redirect('/insider/courses/' . $course->id . '/report#student' . $student_id);
    }

    private function buildGeekPasteIntegrityReport($students, $lessons)
    {
        $stats = [];

        foreach ($students as $student) {
            $stats[$student->id] = [
                'overall' => $this->emptyGeekPasteIntegrityBucket(),
                'chapters' => [],
                'lessons' => [],
                'tasks' => [],
            ];
        }

        foreach ($lessons as $lesson) {
            $chapterId = $lesson->chapter_id ?: 0;

            foreach ($lesson->steps as $step) {
                foreach ($step->tasks as $task) {
                    foreach ($task->solutions as $solution) {
                        if (!isset($stats[$solution->user_id])) {
                            continue;
                        }

                        if (!isset($stats[$solution->user_id]['chapters'][$chapterId])) {
                            $stats[$solution->user_id]['chapters'][$chapterId] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        if (!isset($stats[$solution->user_id]['lessons'][$lesson->id])) {
                            $stats[$solution->user_id]['lessons'][$lesson->id] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        if (!isset($stats[$solution->user_id]['tasks'][$task->id])) {
                            $stats[$solution->user_id]['tasks'][$task->id] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        $stats[$solution->user_id]['overall'] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['overall'], $solution);
                        $stats[$solution->user_id]['chapters'][$chapterId] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['chapters'][$chapterId], $solution);
                        $stats[$solution->user_id]['lessons'][$lesson->id] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['lessons'][$lesson->id], $solution);
                        $stats[$solution->user_id]['tasks'][$task->id] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['tasks'][$task->id], $solution);
                    }
                }
            }
        }

        foreach ($stats as $studentId => $studentStats) {
            $stats[$studentId]['overall'] = $this->finalizeGeekPasteIntegrityBucket($studentStats['overall']);

            foreach ($studentStats['chapters'] as $chapterId => $bucket) {
                $stats[$studentId]['chapters'][$chapterId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }

            foreach ($studentStats['lessons'] as $lessonId => $bucket) {
                $stats[$studentId]['lessons'][$lessonId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }

            foreach ($studentStats['tasks'] as $taskId => $bucket) {
                $stats[$studentId]['tasks'][$taskId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }
        }

        return $stats;
    }

    private function emptyGeekPasteIntegrityBucket()
    {
        return [
            'submissions' => 0,
            'synced' => 0,
            'ai_warnings' => 0,
            'similarity_warnings' => 0,
            'similarity_critical' => 0,
            'similarity_matches_count' => 0,
            'max_llm_probability' => null,
            'max_similarity_percent' => null,
            'has_high_ai_confidence' => false,
            'has_medium_ai_confidence' => false,
            'dismissed' => 0,
            'risk_level' => 'none',
        ];
    }

    private function addSolutionToGeekPasteIntegrityBucket(array $bucket, Solution $solution)
    {
        if (!$solution->hasGeekPasteIntegrityData()) {
            return $bucket;
        }

        $bucket['submissions']++;

        if ($solution->geekpaste_integrity_synced_at || $solution->geekpaste_code_id) {
            $bucket['synced']++;
        }

        if ($solution->geekpaste_integrity_dismissed_at) {
            $bucket['dismissed']++;

            return $bucket;
        }

        if ($solution->geekpaste_ai_warning) {
            $bucket['ai_warnings']++;
        }

        if ($solution->geekpaste_similarity_warning) {
            $bucket['similarity_warnings']++;
        }

        if ($solution->geekpaste_similarity_critical) {
            $bucket['similarity_critical']++;
        }

        $bucket['similarity_matches_count'] += (int) $solution->geekpaste_similarity_matches_count;

        if ($solution->geekpaste_llm_probability !== null) {
            $bucket['max_llm_probability'] = max((int) $solution->geekpaste_llm_probability, (int) ($bucket['max_llm_probability'] ?? 0));
        }

        if ($solution->geekpaste_similarity_max_percent !== null) {
            $bucket['max_similarity_percent'] = max((int) $solution->geekpaste_similarity_max_percent, (int) ($bucket['max_similarity_percent'] ?? 0));
        }

        if ($solution->geekpaste_ai_confidence === 'high') {
            $bucket['has_high_ai_confidence'] = true;
        }

        if ($solution->geekpaste_ai_confidence === 'medium') {
            $bucket['has_medium_ai_confidence'] = true;
        }

        return $bucket;
    }

    private function finalizeGeekPasteIntegrityBucket(array $bucket)
    {
        $maxLlm = $bucket['max_llm_probability'];
        $maxSimilarity = $bucket['max_similarity_percent'];

        if (
            $bucket['similarity_critical'] > 0 ||
            $bucket['has_high_ai_confidence'] ||
            ($maxLlm !== null && $maxLlm >= 80) ||
            ($maxSimilarity !== null && $maxSimilarity >= 95)
        ) {
            $bucket['risk_level'] = 'high';
        } elseif (
            $bucket['ai_warnings'] > 0 ||
            $bucket['similarity_warnings'] > 0 ||
            $bucket['has_medium_ai_confidence'] ||
            ($maxLlm !== null && $maxLlm >= 60) ||
            ($maxSimilarity !== null && $maxSimilarity >= 75)
        ) {
            $bucket['risk_level'] = 'medium';
        } elseif ($bucket['synced'] > 0) {
            $bucket['risk_level'] = 'low';
        }

        return $bucket;
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

    public function deadlines($id)
    {
        $user = Auth::user();
        $course = Course::with('teachers', 'students')->findOrFail($id);
        $isManager = $user->role == 'admin' || $course->teachers->contains($user);
        $deadlines = app(CourseDeadlineList::class)->forCourses(collect([$course]), $user, includeCompleted: true);

        $deadlineMonths = $deadlines->groupBy(fn ($deadline) => $deadline->expiration->format('Y-m'))
            ->map(fn ($month) => $month->groupBy(fn ($deadline) => $deadline->expiration->format('Y-m-d')));

        return view('courses.deadlines', compact('course', 'user', 'isManager', 'deadlines', 'deadlineMonths'));
    }

    public function lessonStatistics($course_id, $lesson_id, Request $request)
    {
        $course = Course::with('teachers')->findOrFail($course_id);
        abort_unless(Auth::user()->role === 'admin' || $course->teachers->contains('id', Auth::id()), 403);
        $lesson = $course->lessons()->whereKey($lesson_id)->firstOrFail();
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $students = $course->statsStudents()->orderBy('name')->orderBy('users.id')->paginate(\App\Support\CourseLessonStatistics::PAGE_SIZE, ['users.id', 'users.name']);
        abort_if($students->currentPage() > $students->lastPage(), 404);
        $allStats = LessonStudentStats::where('course_id', $course->id)->where('lesson_id', $lesson->id)
            ->whereIn('student_id', $course->statsStudents()->select('users.id'))->get();
        $summary = \App\Support\CourseLessonStatistics::summary($allStats, $students->total());
        $stats = $allStats->whereIn('student_id', $students->pluck('id'))->keyBy('student_id');
        return response()->json(['html' => view('courses.partials.lesson-statistics', compact('course', 'lesson', 'students', 'stats', 'summary'))->render()]);
    }

    public function details($id, Request $request)
    {
    // Record action
    ActionLog::record(Auth::user()->id, 'course', $id);
    // Load current user with relations
    $user = Auth::user();
        // Optimized: load only essential relations, defer heavy loading
        $course = Course::with([
            'program.chapters',
            'program.lessons.info',
            'program.lessons' => function($query) {
                $query->orderBy('sort_index');
            },
            'program.lessons.earlyAccesses' => function ($query) use ($id, $user) {
                $query->where('course_id', $id)->where('user_id', $user->id);
            },
            'students' => function($query) {
                $query->select('users.id', 'users.name', 'users.image', 'users.custom_title', 'users.custom_title_expires_at', 'users.avatar_frame', 'users.avatar_frame_expires_at');
            },
            'statsStudents' => function($query) {
                $query->select('users.id', 'users.name', 'users.image', 'users.custom_title', 'users.custom_title_expires_at', 'users.avatar_frame', 'users.avatar_frame_expires_at');
            },
            'statsTeachers',
            'teachers'
        ])->findOrFail($id);

        $allStudents = $course->students;
        $students = $course->statsStudents;
        $statsStudentIds = $students->pluck('id');
        if ($allStudents->contains('id', $user->id)) {
            $statsStudentIds = $statsStudentIds->push($user->id)->unique()->values();
        }
        $statsTeachers = $course->statsTeachers;

        $currentLesson = $course->currentLessonFor($user);
        $currentLessonChapter = $course->program->chapters->firstWhere('id', $currentLesson?->chapter_id);

            //Made this due to some issues on my local server
            $cstudent = [];
            if ($request->has('chapter')) {
                $chapter = $course->program->chapters->where('id', $request->chapter)->first();
                if (!$chapter) {
                    abort(404);
                }
            } elseif ($currentLessonChapter) {
                $chapter = $currentLessonChapter;
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

            $selectedLessonIds = $course->program->lessons->where('chapter_id', $chapter?->id)->pluck('id');
            $isManager = $user->role === 'admin' || $course->teachers->contains('id', $user->id);
            // Material and solution details are needed only for the selected chapter.
            $materialRelations = [
                'program.lessons.steps' => fn ($query) => $query->whereIn('lesson_id', $selectedLessonIds),
                'program.lessons.steps.tasks.deadlines' => function ($query) use ($id) {
                    $query->where('course_id', $id);
                },

                'program.lessons.info',
                'program.lessons.earlyAccesses' => function ($query) use ($id, $user) {
                    $query->where('course_id', $id)->where('user_id', $user->id);
                },
            ];

            if (!$isManager) {
                $materialRelations['program.lessons.steps.tasks.solutions'] = fn ($query) => $query
                    ->where('course_id', $id)->where('user_id', $user->id);
            }
            $course->load($materialRelations);

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
                ->whereIn('student_id', $statsStudentIds)
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

            if ($allStudents->contains('id', $user->id)) {
                // Keep the order
                $lessons = $course->program->lessons->where('chapter_id', $chapter->id)->sortBy('sort_index')->values();

                $steps = $temp_steps;
                $cstudent = $allStudents->where('id', $user->id)->first();
                if ($cstudent) {
                    if (isset($cachedPoints[$cstudent->id])) {
                        $cstudent->percent = $cachedPoints[$cstudent->id]->percent;
                        $cstudent->points = $cachedPoints[$cstudent->id]->points;
                        $cstudent->max_points = $cachedPoints[$cstudent->id]->max_points;
                    } else {
                        $studentsNeedingRecalculate->push($cstudent->id);
                        $cstudent->percent = 0;
                        $cstudent->points = 0;
                        $cstudent->max_points = 0;
                    }
                }
            } else {
                $steps = $temp_steps;
                // For teachers, show all lessons in this chapter
                $lessons = $course->program->lessons->where('chapter_id', $chapter->id)->sortBy('sort_index')->values();
            }

            if ($studentsNeedingRecalculate->isNotEmpty()) {
                RecalculateCoursePoints::dispatch($id, $studentsNeedingRecalculate->unique()->values()->all());
            }

            $isManager = $user->role == 'admin' || $course->teachers->contains($user);
            $lessonCardDeadlines = app(CourseDeadlineList::class)->forCourses(collect([$course]), $user);
            $deadlineNow = now();
            $courseDeadlines = $lessonCardDeadlines->filter(fn ($deadline) => ($deadline instanceof \App\Support\DeadlineRule
                ? $deadline->cutoffAt() : $deadline->expiration->copy()->addDay())->gt($deadlineNow))->values();
            $coursePendingCount = $isManager ? Solution::where('course_id', $course->id)->pendingReview()->count() : 0;

            // Preload lesson statistics for all students and lessons in this chapter
            $lessonStats = LessonStudentStats::where('course_id', $id)
                ->whereIn('lesson_id', $lessons->pluck('id'))
                ->whereIn('student_id', $statsStudentIds)
                ->get()
                ->groupBy('lesson_id')
                ->map(function ($stats) {
                    return $stats->keyBy('student_id');
                });

            // Render view without caching for now (caching causes stale data issues)
        $projectPathStats = $isManager ? collect() : LessonStudentStats::where('course_id', $id)
            ->where('student_id', $user->id)->get(['lesson_id', 'max_points', 'percent'])->keyBy('lesson_id');
        $lessonCards = app(\App\Services\LessonCards::class)->forLessons($course, $lessons, $user, $students);
        return view('courses.details', compact('chapter', 'course', 'user', 'steps', 'students', 'statsTeachers', 'cstudent', 'lessons', 'lessonStats', 'lessonCards', 'courseDeadlines', 'lessonCardDeadlines', 'coursePendingCount', 'currentLesson', 'projectPathStats'));


    }

    public function assessments($id)
    {
        $course = Course::with([
            'program.chapters',
            'program.lessons.steps.tasks',
            'statsStudents.submissions' => fn ($query) => $query->where('course_id', $id),
        ])->findOrFail($id);
        $course->setRelation('students', $course->statsStudents);

        $blockedTaskMap = BlockedTask::where('course_id', $course->id)
            ->get(['user_id', 'task_id'])
            ->mapWithKeys(function ($item) {
                return [$item->user_id . ':' . $item->task_id => true];
            });

        $assessmentChapter = request()->filled('chapter')
            ? $course->program->chapters->firstWhere('id', request()->integer('chapter'))
            : ($course->program->chapters->firstWhere('id', $course->default_chapter_id) ?? $course->program->chapters->first());
        abort_if(request()->filled('chapter') && !$assessmentChapter, 404);
        $assessmentLessons = $assessmentChapter
            ? $course->program->lessons->where('chapter_id', $assessmentChapter->id)
            : collect();
        $pendingReviewPairs = $course->state === 'started'
            ? Solution::where('course_id', $course->id)->pendingReview()->get(['user_id', 'task_id'])
                ->mapWithKeys(fn ($solution) => [$solution->user_id.':'.$solution->task_id => true]) : collect();
        return view('courses.assessments', compact('course', 'blockedTaskMap', 'assessmentLessons', 'assessmentChapter', 'pendingReviewPairs'));
    }

    public function createView()
    {
        $programs = Program::all();
        return view('courses.create', compact('programs'));
    }

    public function editView($id)
    {
        $course = Course::with(['program', 'categories', 'teachers', 'students'])->findOrFail($id);
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
        $course = Course::findOrFail($course_id);
        return view('courses.create_chapter', compact('course'));
    }

    public function editChapterView($course_id, $chapter_id)
    {
        $chapter = ProgramChapter::findOrFail($chapter_id);
        $course = Course::findOrFail($course_id);
        abort_unless((int)$chapter->program_id === (int)$course->program_id, 404);
        return view('courses.edit_chapter', compact('chapter', 'course'));
    }

    public function editChapter($course_id, $chapter_id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $chapter = ProgramChapter::findOrFail($chapter_id);


        $this->validate($request, [
            'name' => 'required|string|max:255',

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

    public function setCurrentLesson($course_id, Request $request)
    {
        $course = Course::with('teachers', 'program.chapters')->findOrFail($course_id);
        abort_unless(Auth::user()->role === 'admin' || $course->teachers->contains('id', Auth::id()), 403);
        $input = $request->validate(['lesson_id' => ['nullable', 'integer', 'min:1'], 'chapter' => ['nullable', 'integer']]);
        $lesson = null;
        if (isset($input['lesson_id'])) {
            $lesson = $course->lessons()->whereKey($input['lesson_id'])->whereHas('steps')
                ->whereHas('chapter', fn ($query) => $query->where('program_id', $course->program_id))->first();
            if (!$lesson) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lesson_id' => 'Выберите урок со ступенями из программы этого курса.',
                ]);
            }
        }
        $chapterId = $course->program->chapters->firstWhere('id', $input['chapter'] ?? null)?->id ?? $lesson?->chapter_id;
        $course->current_lesson_id = $lesson?->id;
        $course->save();

        return redirect('/insider/courses/'.$course->id.($chapterId ? '?chapter='.$chapterId : '').($lesson ? '#lesson-'.$lesson->id : ''))
            ->with('alert-class', 'alert-success')->with('alert-destination', 'head')
            ->with('alert-title', $lesson ? 'Текущий урок выбран' : 'Автоматический выбор урока')
            ->with('alert-text', $lesson ? 'Текущий урок курса: '.$lesson->name.'. Доступ к материалам определяется расписанием.' : 'Для ученика выделяется первый незавершённый доступный урок.');
    }

    public function createChapter($course_id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);


        $this->validate($request, [
            'name' => 'required|string|max:255'
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
        $course = Course::findOrFail($id);
        $inviteChanged = $request->has('invite') && $course->invite !== $request->invite;
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1024',
            'git' => 'nullable|string|max:255',
            'site' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'icon_key' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_merge(['auto'], array_keys(\App\Support\CourseIcons::options())))],
            'telegram' => 'nullable|string|max:255',
            'weekdays' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'invite' => $inviteChanged ? 'required|string|max:253|unique:courses,invite|unique:providers,invite' : 'nullable|string|max:253',
        ]);

        $course->name = $request->name;
        $course->description = clean($request->description);
        $course->git = $request->git;
        $course->site = $request->site;
        $course->image = $request->image;
        if ($request->has('icon_key')) {
            $course->icon_key = $request->icon_key === 'auto' ? null : $request->icon_key;
        }
        $course->telegram = $request->telegram;
        $course->start_date = $request->start_date;
        $course->weekdays = $request->weekdays ? $request->weekdays : "";
        if (\Auth::user()->role == 'admin') {
            $teacherIds = collect($request->input('teachers', []))->map(fn($id) => (int) $id)->filter()->unique()->values();
            $hiddenTeacherIds = collect($request->input('hidden_teachers', []))
                ->map(fn($id) => (int) $id)
                ->intersect($teacherIds)
                ->values();
            $course->teachers()->sync($teacherIds->mapWithKeys(function ($teacherId) use ($hiddenTeacherIds) {
                return [$teacherId => ['hidden_from_stats' => $hiddenTeacherIds->contains($teacherId)]];
            })->all());

            $categoryIds = collect($request->input('categories', []))->map(fn($id) => (int) $id)->filter()->unique()->values();
            $course->categories()->sync($categoryIds->all());
            if ($request->mode != null)
                $course->mode = $request->mode;
        }

        $studentIds = collect($request->input('students', []))->map(fn($id) => (int) $id)->filter()->unique()->values();
        $hiddenStudentIds = collect($request->input('hidden_students', []))
            ->map(fn($id) => (int) $id)
            ->intersect($studentIds)
            ->values();
        $remoteByStudent = $course->students->mapWithKeys(function ($student) {
            return [$student->id => (bool) ($student->pivot->is_remote ?? false)];
        });

        $course->students()->sync($studentIds->mapWithKeys(function ($studentId) use ($hiddenStudentIds, $remoteByStudent) {
            return [$studentId => [
                'hidden_from_stats' => $hiddenStudentIds->contains($studentId),
                'is_remote' => $remoteByStudent->get($studentId, false),
            ]];
        })->all());

        foreach ($studentIds as $student_id) {
            if (!$course->is_open) {
                $user = User::findOrFail($student_id);
                if ($user->role == 'novice') {
                    $user->role = 'student';
                    $user->save();
                }
            }
        }

        if ($inviteChanged) {
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

    public function generatePoster($id, CoursePosterGenerator $posterGenerator)
    {
        @set_time_limit(180);

        $course = Course::with('program')->findOrFail($id);

        if (!$course->program) {
            $this->make_error_alert('Плакат не получился', 'У курса не выбрана программа, поэтому некуда сохранить общий плакат.');

            return redirect('/insider/courses/' . $course->id . '/edit');
        }

        try {
            $posterGenerator->generateForCourse($course);
        } catch (\Throwable $e) {
            \Log::error('Course poster generation failed', [
                'course_id' => $course->id,
                'program_id' => $course->program_id,
                'message' => $e->getMessage(),
            ]);

            $this->make_error_alert('Плакат не получился', 'Не удалось сгенерировать плакат программы. Попробуйте позже.');

            return redirect('/insider/courses/' . $course->id . '/edit');
        }

        $this->make_success_alert('Плакат готов', 'Новый плакат программы создан по описанию этого курса и будет показываться в комнате учеников.');

        return redirect('/insider/courses/' . $course->id . '/edit');
    }

    public function create(Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);


        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1024',
            'image' => 'image|max:10240',
            'icon_key' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_merge(['auto'], array_keys(\App\Support\CourseIcons::options())))],
        ]);

        $course = new Course();
        $course->icon_key = $request->icon_key === 'auto' ? null : $request->icon_key;
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
        $this->validate($request, [
            'invite' => 'required|string|max:255',
        ]);

        if ($request->invite == null || $request->invite == "") {
            $this->make_error_alert('Ошибка!', 'Курс с таким приглашением не найден.');
            return $this->backException();
        }
        $user = User::findOrFail(Auth::User()->id);
        $invitation = app(\App\Services\CourseEnrollment::class)->invitation($request->invite);
        $course = $invitation['course'] ?? null;
        $remote = $invitation['remote'] ?? false;

        if ($course == null) {
            $this->make_error_alert('Ошибка!', 'Курс с таким приглашением не найден.');
            return $this->backException();
        }

        if (!app(\App\Services\CourseEnrollment::class)->enroll($course, $user, $remote)) {
            $this->make_error_alert('Ошибка!', 'Вы уже зачислены на курс "' . $course->name . '".');
            return $this->backException();
        }
        if ($user->role == 'novice') {
            $user->role = 'student';
            $user->save();
        }

        $this->make_success_alert('Успех!', 'Вы присоединились к курсу "' . $course->name . '".');


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
        app(\App\Services\CourseEnrollment::class)->enroll($course, $user);


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

    public function exportChapterPoints($course_id, $chapter_id)
    {
        $course = Course::with('statsStudents')->findOrFail($course_id);
        $chapter = ProgramChapter::where('program_id', $course->program_id)->findOrFail($chapter_id);
        $lessons = $chapter->lessons()->where('program_id', $course->program_id)->get();
        $students = $course->statsStudents;
        $studentIds = $students->pluck('id');

        $statsByLesson = $lessons->mapWithKeys(function ($lesson) use ($course, $studentIds) {
            return [$lesson->id => LessonStudentStats::recalculateForLesson($course->id, $lesson->id, $studentIds)];
        });

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Chapter points export');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Баллы');

        $headers = ['Имя ученика'];
        foreach ($lessons as $lesson) {
            $headers[] = $lesson->name . ' — Балл';
            $headers[] = $lesson->name . ' — Максимальный балл';
        }
        $headers[] = 'Итого баллов';
        $headers[] = 'Итого максимальный балл';
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
        }

        $row = 2;
        foreach ($students as $student) {
            $sheet->setCellValueExplicit([1, $row], $student->name, DataType::TYPE_STRING);
            $column = 2;
            $totalPoints = 0;
            $totalMaxPoints = 0;
            foreach ($lessons as $lesson) {
                $stats = $statsByLesson->get($lesson->id)->get($student->id);
                $points = $stats ? (int) $stats->points : 0;
                $maxPoints = $stats ? (int) $stats->max_points : 0;
                $sheet->setCellValue([$column++, $row], $points);
                $sheet->setCellValue([$column++, $row], $maxPoints);
                $totalPoints += $points;
                $totalMaxPoints += $maxPoints;
            }
            $sheet->setCellValue([$column++, $row], $totalPoints);
            $sheet->setCellValue([$column, $row], $totalMaxPoints);
            $row++;
        }

        $lastRow = max(1, $row - 1);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $totalColumn = Coordinate::stringFromColumnIndex(count($headers) - 1);
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF2F8'],
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
        $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($totalColumn . '1:' . $lastColumn . $lastRow)->getFont()->setBold(true);
        if ($lastRow >= 2) {
            $sheet->getStyle('B2:' . $lastColumn . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        for ($column = 1; $column <= count($headers); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $sheet->freezePane('B2');
        $sheet->setAutoFilter('A1:' . $lastColumn . $lastRow);

        $tempPath = tempnam(sys_get_temp_dir(), 'chapter-points-');
        if ($tempPath === false) {
            $spreadsheet->disconnectWorksheets();
            abort(500);
        }
        try {
            (new Xlsx($spreadsheet))->save($tempPath);
        } catch (\Throwable $exception) {
            unlink($tempPath);
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $safeChapterName = $this->sanitizeFileName($chapter->name);
        $fileName = 'chapter-' . $chapter->id . '-points' . ($safeChapterName ? '-' . $safeChapterName : '') . '.xlsx';

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
