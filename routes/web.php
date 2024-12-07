<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/home', function () {

    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect('/insider');
    }
    return redirect('/login');

});

Route::get('/start-exam', function () {
    return redirect('/open/steps/731');
});

Route::get('/1561test', function () {
    return redirect('https://forms.gle/KzJ9oAn6tT4eafu47');
});

Route::get('/vk-meetup', function () {
    return redirect('/open/steps/745');
});

Route::get('/telegram-bot', function () {
    return redirect('/open/steps/647');
});

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect('/insider');
    }
    return redirect('/login');

});

Auth::routes(['verify' => true]);

Route::prefix('open')->group(function () {
    Route::get('/steps/{id}', 'OpenStepsController@details');
});

Route::get('/aesthethics', function () {
    return view('aesthethics');
});

Route::get('/feedback/{key}', 'DetailedFeedbackController@feedback_view');
Route::post('/feedback/{key}', 'DetailedFeedbackController@feedback');

Route::get('/articles', 'ArticlesController@open_index');
Route::get('/articles/{id}', 'ArticlesController@details');
Route::get('/courses', 'CourseCategoriesController@index');
Route::get('/courses/{id}', 'CourseCategoriesController@details');

Route::get('/textbook/{id}', 'TextbookController@index');
Route::get('/textbook/{id}/lesson/{lesson_id}', 'TextbookController@lesson');
Route::get('/textbook/{id}/edit/{step_id}', 'TextbookController@edit_step');

Route::get('/categories/create', 'CourseCategoriesController@createView');
Route::post('/categories/create', 'CourseCategoriesController@create');
Route::get('/categories/{id}', 'CourseCategoriesController@details');
Route::get('/categories/{id}/start', 'CourseCategoriesController@start');
Route::get('/categories/{id}/stop', 'CourseCategoriesController@stop');
Route::get('/categories/{id}/edit', 'CourseCategoriesController@editView');
Route::post('/categories/{id}/edit', 'CourseCategoriesController@edit');

Route::get('/games', 'GamesController@index');
Route::get('/games/{id}', 'GamesController@play');
Route::get('/games/{id}/frame', 'GamesController@frame');
Route::get('/insider/games/{id}/viewsource', 'GamesController@viewsource');

Route::get('/activate', 'BotController@activateView');
Route::get('/activate/success', 'BotController@successView');
Route::post('/activate', 'BotController@activate');

Route::prefix('insider')->middleware('verified')->group(function () {

    #TODO Check
    Route::get('/', function () {
        return redirect('/insider/courses');
    });

    Route::get('/jwt', 'RemoteAuthController@remoteAuth');


    Route::post('/vk/send', 'BotController@send');
    Route::get('/vk/send', 'BotController@sendView');

    Route::get('/articles', 'ArticlesController@index');
    Route::get('/articles/create', 'ArticlesController@createView');
    Route::post('/articles/create', 'ArticlesController@create');
    Route::get('/articles/{id}', 'ArticlesController@details');
    Route::get('/articles/{id}/edit', 'ArticlesController@editView');
    Route::post('/articles/{id}/edit', 'ArticlesController@edit');
    Route::get('/articles/{id}/delete', 'ArticlesController@delete');
    Route::get('/articles/{id}/ask_review', 'ArticlesController@ask_review');
    Route::get('/articles/{id}/publish', 'ArticlesController@publish');
    Route::get('/articles/{id}/draft', 'ArticlesController@publish');
    Route::post('/articles/{id}/comment', 'ArticlesController@comment');
    Route::get('/articles/comments/{id}/delete', 'ArticlesController@delete_comment');

    Route::get('/articles/{id}/downvote', 'ArticlesController@downvote');
    Route::get('/articles/{id}/upvote', 'ArticlesController@upvote');

    Route::get('/games', 'GamesController@index');
    Route::post('/games', 'GamesController@store');
    Route::get('/games/{id}', 'GamesController@play');

    Route::get('/games/{id}/upvote', 'GamesController@upvote');
    Route::get('/games/{id}/downvote', 'GamesController@downvote');
    Route::post('/games/{id}/comment', 'GamesController@comment');
    Route::get('/games/{id}/reward', 'GamesController@reward')->middleware('noysreward');
    Route::post('/games/{id}/reward', 'GamesController@sendReward')->middleware('noysreward');
    Route::get('/games/comments/{cid}/delete', 'GamesController@commentDelete')->middleware('gameowncomment');
    Route::post('/games/{id}', 'GamesController@update')->middleware('gameown');

    Route::get('/games/{id}/frame', 'GamesController@frame');
    Route::get('/games/{id}/edit', 'GamesController@edit')->middleware('gameown');
    Route::get('/games/{id}/ide', 'GamesController@ide')->middleware('gameown');
    Route::get('/games/{id}/delete', 'GamesController@delete')->middleware('gameown');
    Route::get('/games/create', 'GamesController@create');

    Route::get('/market', 'MarketController@index');
    Route::get('/market/create', 'MarketController@createView');
    Route::post('/market/create', 'MarketController@create');
    Route::get('/market/{id}/edit', 'MarketController@editView');
    Route::post('/market/{id}/edit', 'MarketController@edit');
    Route::get('/market/{id}/buy', 'MarketController@buy');
    Route::get('/market/ship/{id}', 'MarketController@ship');


    Route::get('/scales', 'ScalesController@index');
    Route::get('/scales/create', 'ScalesController@createView');
    Route::post('/scales/create', 'ScalesController@create');
    Route::get('/scales/{id}/edit', 'ScalesController@editView');
    Route::post('/scales/{id}/edit', 'ScalesController@edit');
    Route::get('/scales/{id}', 'ScalesController@details');
    Route::get('/scales/{id}/results/add', 'ScalesController@createResultView');
    Route::post('/scales/{id}/results/add', 'ScalesController@createResult');
    Route::get('/scales/{id}/results/{result_id}/edit', 'ScalesController@editResultView');
    Route::post('/scales/{id}/results/{result_id}/edit', 'ScalesController@editResult');
    Route::get('/scales/{id}/results/{result_id}/delete', 'ScalesController@deleteResult');
    Route::get('/scales/{id}/results/{result_id}/tasks/add', 'ScalesController@createTaskForm');
    Route::post('/scales/{id}/results/{result_id}/tasks/add', 'ScalesController@createTask');
    Route::get('/scales/{id}/results/{result_id}/tasks/{task_id}/edit', 'ScalesController@editTaskForm');
    Route::post('/scales/{id}/results/{result_id}/tasks/{task_id}/edit', 'ScalesController@editTask');
    Route::get('/scales/{id}/results/{result_id}/tasks/{task_id}/delete', 'ScalesController@deleteTask');

    Route::get('/courses', 'CoursesController@index')->name('Courses');

    Route::get('/courses/create', 'CoursesController@createView')->name('Create course');
    Route::post('/courses/create', 'CoursesController@create');

    Route::get('/courses/{id}/', 'CoursesController@details');
    Route::get('/courses/{id}/enroll', 'CoursesController@enroll');
    Route::get('/courses/{id}/report', 'CoursesController@report');
    Route::get('/courses/{id}/edit', 'CoursesController@editView');
    Route::get('/courses/{id}/start', 'CoursesController@start');
    Route::get('/courses/{id}/stop', 'CoursesController@stop');
    Route::post('/courses/{id}/edit', 'CoursesController@edit');
    Route::get('/courses/{id}/assessments', 'CoursesController@assessments');
    Route::get('/courses/{id}/export', 'CoursesController@export');
    Route::get('/courses/{id}/chapter', 'CoursesController@createChapterView');
    Route::post('/courses/{id}/chapter', 'CoursesController@createChapter');
    Route::get('/courses/{id}/add_sdl_lesson', 'CoursesController@addSdlLesson');
    Route::post('/courses/{id}/sdl_idea', 'CoursesController@setSdlIdea');
    Route::get('/courses/{id}/sdl_idea', 'CoursesController@setSdlIdea');

    Route::get('/courses/{course_id}/chapters/{chapter_id}/edit', 'CoursesController@editChapterView');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/upper', 'CoursesController@makeChapterUpper');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/lower', 'CoursesController@makeChapterLower');
    Route::post('/courses/{course_id}/chapters/{chapter_id}/edit', 'CoursesController@editChapter');


    Route::get('/programs', 'ProgramsController@index');
    Route::get('/programs/create', 'ProgramsController@createView')->name('Create program');
    Route::post('/programs/create', 'ProgramsController@create');
    Route::get('/programs/{id}/', 'ProgramsController@details');
    Route::get('/programs/{id}/edit', 'ProgramsController@editView');
    Route::post('/programs/{id}/edit', 'ProgramsController@edit');
    Route::get('/courses/{id}/create', 'LessonsController@createView');
    Route::post('/courses/{id}/create', 'LessonsController@create');


    Route::get('/courses/{course_id}/lessons/{id}/edit', 'LessonsController@editView');
    Route::post('/courses/{course_id}/lessons/{id}/edit', 'LessonsController@edit');
    Route::get('/courses/{course_id}/lessons/{id}/export', 'LessonsController@export');
    Route::get('/courses/{course_id}/lessons/{id}/lower', 'LessonsController@makeLower');
    Route::get('/courses/{course_id}/lessons/{id}/upper', 'LessonsController@makeUpper');
    Route::get('/courses/{course_id}/lessons/{id}/delete', 'LessonsController@delete');


    Route::get('/courses/{course_id}/lessons/{id}/create', 'StepsController@createView');
    Route::post('/courses/{course_id}/lessons/{id}/create', 'StepsController@create');
    Route::get('/courses/{course_id}/steps/{id}', 'StepsController@details');
    Route::get('/courses/{course_id}/perform/{id}', 'StepsController@perform');
    Route::get('/courses/{course_id}/steps/{id}/edit', 'StepsController@editView');
    Route::get('/courses/{course_id}/steps/{id}/lower', 'StepsController@makeLower');
    Route::get('/courses/{course_id}/steps/{id}/upper', 'StepsController@makeUpper');
    Route::get('/courses/{course_id}/steps/{id}/delete', 'StepsController@delete');
    Route::post('/courses/{course_id}/steps/{id}/edit', 'StepsController@edit');
    Route::post('/courses/{course_id}/steps/{id}/question', 'StepsController@question');
    Route::post('/courses/{course_id}/steps/{id}/task', 'TasksController@create');

    Route::get('/courses/{course_id}/questions/{id}/delete', 'StepsController@deleteQuestion');
    Route::get('/courses/{course_id}/tasks/{id}/delete', 'TasksController@delete');
    Route::get('/courses/{course_id}/tasks/{id}/edit', 'TasksController@editForm');

    Route::get('/courses/{course_id}/tasks/{id}/up', 'TasksController@toPreviousTask');
    Route::get('/courses/{course_id}/tasks/{id}/down', 'TasksController@toNextTask');
    Route::get('/courses/{course_id}/tasks/{id}/left', 'TasksController@makeLower');
    Route::get('/courses/{course_id}/tasks/{id}/right', 'TasksController@makeUpper');
    Route::get('/courses/{course_id}/tasks/{id}/peer', 'TasksController@reviewTable');
    Route::post('/courses/{course_id}/tasks/{id}/deadline', 'TasksController@makeDeadline');
        

    Route::post('/courses/{course_id}/tasks/{id}/edit', 'TasksController@edit');
    Route::post('/courses/{course_id}/tasks/{id}/solution', 'TasksController@postSolution');
    Route::get('/courses/{course_id}/tasks/{id}/solution/{solution_id}/recheck', 'TasksController@askForRecheck');
    Route::get('/courses/{course_id}/tasks/{id}/phantom', 'TasksController@phantomSolution');
    Route::get('/courses/{course_id}/tasks/{id}/student/{student_id}', 'TasksController@reviewSolutions');
    Route::post('/courses/{course_id}/solution/{id}', 'TasksController@estimateSolution');
    Route::get('/invite', 'CoursesController@invite');

    Route::get('/community', 'ProfileController@index');
    Route::get('/profile/{id?}', 'ProfileController@details');


    Route::get('/profile/{id}/edit', 'ProfileController@editView');
    Route::post('/profile/{id}/edit', 'ProfileController@edit');
    Route::post('/profile/{id}/course', 'ProfileController@course');
    Route::get('/profile/delete-course/{id}', 'ProfileController@deleteCourse');
    Route::get('/profile/{user_id}/delete-course/{course_id}', 'ProfileController@deleteCurrentCourse');
    Route::post('/profile/{user_id}/money', 'ProfileController@addMoney');

    Route::get('/projects/create', 'ProjectsController@createView');
    Route::post('/projects/create', 'ProjectsController@create');
    Route::get('/projects/{id}', 'ProjectsController@details');

    Route::get('/projects/{id}/reward', 'ProjectsController@rewardView');
    Route::post('/projects/{id}/reward', 'ProjectsController@reward');
    Route::get('/projects/{id}/edit', 'ProjectsController@editView');
    Route::post('/projects/{id}/edit', 'ProjectsController@edit');
    Route::get('/projects/{id}/delete', 'ProjectsController@deleteProject');
    Route::post('/projects/{id}/review', 'ProjectsController@review');
    Route::get('/projects/{id}/ask_review', 'ProjectsController@ask_review');
    Route::get('/projects', 'ProjectsController@index');

    Route::get('/ideas/create', 'IdeasController@createView');
    Route::post('/ideas/create', 'IdeasController@create');
    Route::get('/ideas/{id}', 'IdeasController@details');
    Route::get('/ideas/{id}/approve', 'IdeasController@approve');
    Route::get('/ideas/{id}/decline', 'IdeasController@decline');

    Route::get('/ideas/{id}/edit', 'IdeasController@editView');
    Route::post('/ideas/{id}/edit', 'IdeasController@edit');
    Route::get('/ideas/{id}/delete', 'IdeasController@delete');
    Route::get('/ideas', 'IdeasController@index');

    // forum
    Route::get('/forum', 'ForumController@index');
    Route::get('/forum/create', 'ForumController@createView');
    Route::get('/forum/{id}', 'ForumController@details');
    Route::get('/forum/{thread_id}/delete/{id}', 'ForumController@delete');
    Route::get('/forum/{thread_id}/edit/{id}', 'ForumController@editView');
    Route::get('/forum/{thread_id}/upvote/{id}', 'ForumController@upvote');
    Route::get('/forum/{thread_id}/downvote/{id}', 'ForumController@downvote');
    Route::post('/forum/{thread_id}/edit/{id}', 'ForumController@edit');
    Route::post('/forum/{id}/answer', 'ForumController@answer');
    Route::get('/forum/{id}/subscribe', 'ForumController@subscribe');
    Route::get('/forum/{id}/unsubscribe', 'ForumController@unsubscribe');
    Route::post('/forum/{thread_id}/comment/{id}', 'ForumController@comment');
    Route::post('/forum/create', 'ForumController@createThread');

    Route::get('/events', 'EventController@event_view');
    Route::get('/events/old', 'EventController@old_events_view');
    Route::get('/events/create', 'EventController@add_event_view');
    Route::post('/events/create', 'EventController@add_event');
    Route::get('/events/{id}', 'EventController@current_event');
    Route::get('/events/{id}/api', function ($ev) {
        $ev = \App\Event::find($ev);
        return json_encode([
            'id' => $ev->id,
            'likes' => $ev->userLikes()->count()
        ]);
    });
    Route::get('/events/{id}/go', 'EventController@go_event');
    Route::get('/events/{id}/left', 'EventController@left_event');
    Route::get('/events/{id}/like', 'EventController@like_event');
    Route::get('/events/{id}/dislike', 'EventController@dislike_event');
    Route::get('/events/{id}/add_org', 'EventController@add_org');
    Route::post('/events', 'EventController@event_view');
    Route::post('/events/old', 'EventController@old_events_view');
    Route::get('/events/{id}/edit', 'EventController@edit_event_view');
    Route::post('/events/{id}/edit', 'EventController@edit_event');
    Route::get('/events/{id}/delete', 'EventController@del_event');
    Route::get('/events/{id}/prize', 'EventController@prize_view');
    Route::post('/events/{id}/prize', 'EventController@prize');
    Route::get('/events/{id}/delete_comm/{id2}', 'EventController@del_comment');

    Route::post('/events/{id}', 'EventController@add_comment');

    Route::get('/core/{id}', 'CoreController@index');
    Route::get('/core/{id}/node/{node_id}', 'CoreController@subcore');
    Route::get('/core/network/{id}', 'CoreController@get_core');
    Route::get('/core/network/', 'CoreController@get_versions');

    Route::get('/core/import', 'CoreController@import_core_form');
    Route::post('/core/import', 'CoreController@import_core')->middleware('teacher');
    Route::get('/core/importa', 'CoreController@import_core')->middleware('teacher');

    Route::get('/themes', 'ThemesController@index');
    Route::get('/themes/{id}', 'ThemesController@details');
    Route::get('/themes/{id}/css', 'ThemesController@getCSS');

    Route::get('/themes/{id}/js', 'ThemesController@getJS');
    Route::get('/themes/{id}/buy', 'ThemesController@buy');

    Route::get('/themes/{id}/wear', 'ThemesController@wear');
    Route::get('/themes/{id}/takeoff', 'ThemesController@takeOff');
    

    Route::get('/themes/create', 'ThemesController@createView')->middleware('teacher');
    Route::post('/themes/create', 'ThemesController@create')->middleware('teacher');
    Route::get('/themes/{id}/edit', 'ThemesController@editView')->middleware('teacher');
    Route::post('/themes/{id}/edit', 'ThemesController@edit')->middleware('teacher');

    Route::get('/core/editor', 'CoreController@editor')->middleware('teacher');

    Route::get('/testmail', function () {
        $user = \App\User::findOrFail(1);
        $when = \Carbon\Carbon::now()->addSeconds(1);
        $user->notify((new \App\Notifications\NewSolution())->delay($when));
    });

    /*

    Route::get('/migrate_to_lessons', function () {
        $courses = \App\Course::all();
        foreach ($courses as $course)
        {
            foreach ($course->steps as $step)
            {
                $lesson = new \App\Lesson();
                $lesson->name = $step->name;
                $lesson->start_date = $step->start_date;
                $lesson->description = $step->description;
                $lesson->course_id = $step->course_id;
                $lesson->save();

                $step->lesson_id = $lesson->id;
                $step->save();
            }
        }

    });

    Route::get('/migrate_to_programs', function () {
        $courses = \App\Course::all();
        foreach ($courses as $course)
        {
            $program = new \App\Program();
            $program->name = $course->name;
            $program->save();
            $program->authors()->attach(1);

            $course->program_id = $program->id;
            $course->save();

            \DB::table('lessons')->where('course_id', $course->id)->update(['program_id'=> $program->id]);
            foreach ($course->lessons as $lesson)
            {
                $lesson->program_id = $program->id;
                foreach ($lesson->steps as $step)
                {
                    $step->program_id = $program->id;
                    $step->save();
                }
                $lesson->save();

                foreach ($step->tasks as $task)
                {
                    \DB::table('solutions')->where('task_id', $task->id)->update(['course_id'=> $course->id]);
                }
            }
        }

    });

    Route::get('/clean_empty_programs', function () {
        $programs = \App\Program::all();
        foreach ($programs as $program)
        {
            if (count($program->courses) == 0)
            {
                $program->delete();
            }
        }

    });

    Route::get('/open_all_lessons', function () {
        $courses = \App\Course::all();
        foreach ($courses as $course)
        {
            foreach ($course->lessons as $lesson)
            {
                $lesson->setStartDate($course, '2016-01-01');
            }

        }
        echo 'ok';

    });

    Route::get('/set_solution_courses', function () {
        $solutions = \App\Solution::where('course_id', null)->get();
        foreach ($solutions as $solution)
        {
            $course = \App\Course::where('program_id', $solution->task->step->program_id)->first();
            $solution->course_id = $course->id;
            $solution->save();
        }

    });

    Route::get('/set_birthdays', function () {
        $data = \Carbon\Carbon::now();
        $data1 = $data->addMonth(-1)->copy();
        $data2 = $data->addMonth(-1)->copy();
        $data3 = $data->addMonth(-1)->copy();

        $students = \App\User::all()->filter(function ($user) use ($data1, $data2, $data3) {
            return $user->birthday != null and ($user->birthday->month == $data1->month or $user->birthday->month == $data2->month or $user->birthday->month == $data3->month);
        });
        dd($students);
        foreach ($students as $student) {
            $age = $data->year - $student->birthday->year;
            \App\CoinTransaction::register($student->id, $age, "ДР 2019");
        }
    });*/

});


Route::get('media/{dir}/{name}', 'MediaController@index');
