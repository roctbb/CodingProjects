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

Route::get('/feedback/{key}', 'DetailedFeedbackController@feedback_view');
Route::post('/feedback/{key}', 'DetailedFeedbackController@feedback');

Route::get('/courses', 'CourseCategoriesController@index');
Route::get('/courses/{id}', 'CourseCategoriesController@details');

Route::get('/textbook/{id}', function () {
    return redirect('/courses', 301);
});
Route::get('/textbook/{id}/lesson/{lesson_id}', function () {
    return redirect('/courses', 301);
});
Route::get('/textbook/{id}/edit/{step_id}', function () {
    return redirect('/courses', 301);
});

Route::get('/categories/create', 'CourseCategoriesController@createView');
Route::post('/categories/create', 'CourseCategoriesController@create');
Route::get('/categories/{id}', 'CourseCategoriesController@details');
Route::post('/categories/{id}/start', 'CourseCategoriesController@start');
Route::post('/categories/{id}/stop', 'CourseCategoriesController@stop');
Route::get('/categories/{id}/edit', 'CourseCategoriesController@editView');
Route::post('/categories/{id}/edit', 'CourseCategoriesController@edit');

Route::prefix('insider')->middleware('verified')->group(function () {

    #TODO Check
    Route::get('/', function () {
        return redirect('/insider/courses');
    });

    Route::get('/jwt', 'RemoteAuthController@remoteAuth');

    // YandexGPT text improvement API
    Route::post('/yandexgpt/improve-text', 'YandexGPTController@improveText');

    Route::get('/market', 'MarketController@index');
    Route::get('/market/orders', 'MarketController@orders');
    Route::get('/market/create', 'MarketController@createView');
    Route::post('/market/create', 'MarketController@create');
    Route::get('/market/{id}/edit', 'MarketController@editView');
    Route::post('/market/{id}/edit', 'MarketController@edit');
    Route::get('/market/{id}/buy', 'MarketController@buy');
    Route::get('/market/ship/{id}', 'MarketController@ship');
    Route::get('/market/cancel/{id}', 'MarketController@cancel');


    Route::get('/courses', 'CoursesController@index')->name('Courses');

    Route::get('/courses/create', 'CoursesController@createView')->name('Create course');
    Route::post('/courses/create', 'CoursesController@create');

    Route::get('/courses/{id}/', 'CoursesController@details');
    Route::post('/courses/{id}/enroll', 'CoursesController@enroll');
    Route::get('/courses/{id}/report', 'CoursesController@report');
    Route::get('/courses/{id}/blocked', 'CoursesController@blocked');
    Route::get('/courses/{id}/edit', 'CoursesController@editView');
    Route::post('/courses/{id}/start', 'CoursesController@start');
    Route::post('/courses/{id}/stop', 'CoursesController@stop');
    Route::post('/courses/{id}/edit', 'CoursesController@edit');
    Route::get('/courses/{id}/assessments', 'CoursesController@assessments');
    Route::get('/courses/{id}/export', 'CoursesController@export');
    Route::get('/courses/{id}/export-md', 'CoursesController@exportMarkdown');
    Route::get('/courses/{id}/chapter', 'CoursesController@createChapterView');
    Route::post('/courses/{id}/chapter', 'CoursesController@createChapter');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/edit', 'CoursesController@editChapterView');
    Route::post('/courses/{course_id}/chapters/{chapter_id}/upper', 'CoursesController@makeChapterUpper');
    Route::post('/courses/{course_id}/chapters/{chapter_id}/lower', 'CoursesController@makeChapterLower');
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
    Route::get('/courses/{course_id}/lessons/{id}/export-md', 'LessonsController@exportMarkdown');
    Route::post('/courses/{course_id}/lessons/{id}/lower', 'LessonsController@makeLower');
    Route::post('/courses/{course_id}/lessons/{id}/upper', 'LessonsController@makeUpper');
    Route::get('/courses/{course_id}/lessons/{id}/delete', 'LessonsController@delete');


    Route::get('/courses/{course_id}/lessons/{id}/create', 'StepsController@createView');
    Route::post('/courses/{course_id}/lessons/{id}/create', 'StepsController@create');
    Route::get('/courses/{course_id}/steps/{id}', 'StepsController@details');
    Route::get('/courses/{course_id}/perform/{id}', 'StepsController@perform');
    Route::get('/courses/{course_id}/steps/{id}/edit', 'StepsController@editView');
    Route::post('/courses/{course_id}/steps/{id}/lower', 'StepsController@makeLower');
    Route::post('/courses/{course_id}/steps/{id}/upper', 'StepsController@makeUpper');
    Route::delete('/courses/{course_id}/steps/{id}/delete', 'StepsController@delete');
    Route::post('/courses/{course_id}/steps/{id}/edit', 'StepsController@edit');
    Route::post('/courses/{course_id}/steps/{id}/question', 'StepsController@question');
    Route::post('/courses/{course_id}/steps/{id}/task', 'TasksController@create');

    Route::delete('/courses/{course_id}/questions/{id}/delete', 'StepsController@deleteQuestion');
    Route::delete('/courses/{course_id}/tasks/{id}/delete', 'TasksController@delete');
    Route::get('/courses/{course_id}/tasks/{id}/edit', 'TasksController@editForm');

    Route::post('/courses/{course_id}/tasks/{id}/up', 'TasksController@toPreviousTask');
    Route::post('/courses/{course_id}/tasks/{id}/down', 'TasksController@toNextTask');
    Route::post('/courses/{course_id}/tasks/{id}/left', 'TasksController@makeLower');
    Route::post('/courses/{course_id}/tasks/{id}/right', 'TasksController@makeUpper');
    Route::get('/courses/{course_id}/tasks/{id}/peer', 'TasksController@reviewTable');
    Route::post('/courses/{course_id}/tasks/{id}/deadline', 'TasksController@makeDeadline');
    Route::post('/courses/{course_id}/tasks/{id}/recheck-all', 'TasksController@recheckAllSolutions');


    Route::post('/courses/{course_id}/tasks/{id}/edit', 'TasksController@edit');
    Route::post('/courses/{course_id}/tasks/{id}/solution', 'TasksController@postSolution');
    Route::post('/courses/{course_id}/tasks/{id}/solution/{solution_id}/recheck', 'TasksController@askForRecheck');
    Route::post('/courses/{course_id}/tasks/{id}/phantom', 'TasksController@phantomSolution');
    Route::get('/courses/{course_id}/tasks/{id}/student/{student_id}', 'TasksController@reviewSolutions');
    Route::post('/courses/{course_id}/tasks/{id}/block/{student_id}', 'TasksController@blockStudent');
    Route::post('/courses/{course_id}/tasks/{id}/unblock/{student_id}', 'TasksController@unblockStudent');
    Route::post('/courses/{course_id}/solution/{id}', 'TasksController@estimateSolution');
    Route::post('/invite', 'CoursesController@invite');

    Route::get('/community', 'ProfileController@index');
    Route::get('/profile/{id?}', 'ProfileController@details');


    Route::get('/profile/{id}/edit', 'ProfileController@editView');
    Route::post('/profile/{id}/edit', 'ProfileController@edit');
    Route::post('/profile/{id}/course', 'ProfileController@course');
    Route::get('/profile/delete-course/{id}', 'ProfileController@deleteCourse');
    Route::get('/profile/{user_id}/delete-course/{course_id}', 'ProfileController@deleteCurrentCourse');
    Route::post('/profile/{user_id}/money', 'ProfileController@addMoney');

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
