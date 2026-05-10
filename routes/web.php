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
Route::post('/telegram/webhook/{secret?}', 'TelegramBotController@webhook');

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


Route::prefix('insider')->middleware('verified')->group(function () {

    #TODO Check
    Route::get('/', function () {
        return redirect('/insider/courses');
    });

    Route::get('/jwt', 'RemoteAuthController@remoteAuth');

    // ChatGPT text improvement API
    Route::post('/chatgpt/improve-text', 'YandexGPTController@improveText');
    Route::post('/yandexgpt/improve-text', 'YandexGPTController@improveText');

    Route::get('/market', 'MarketController@index');
    Route::get('/market/orders', 'MarketController@orders');
    Route::get('/market/create', 'MarketController@createView');
    Route::post('/market/create', 'MarketController@create');
    Route::get('/market/{id}/edit', 'MarketController@editView');
    Route::post('/market/{id}/edit', 'MarketController@edit');
    Route::get('/market/{id}/archive', 'MarketController@archive');
    Route::get('/market/{id}/restore', 'MarketController@restore');
    Route::get('/market/{id}/buy', 'MarketController@buy');
    Route::post('/market/{id}/bid', 'MarketController@bid');
    Route::get('/market/{id}/finish-auction', 'MarketController@finishAuction');
    Route::get('/market/ship/{id}', 'MarketController@ship');
    Route::get('/market/cancel/{id}', 'MarketController@cancel');


    Route::get('/courses', 'CoursesController@index')->name('Courses');
    Route::get('/pulse', 'CoursesController@pulse');
    Route::get('/reviews', 'CoursesController@reviews');
    Route::post('/reviews/reset-pending', 'CoursesController@resetPendingReviews');

    Route::get('/courses/create', 'CoursesController@createView')->name('Create course');
    Route::post('/courses/create', 'CoursesController@create');

    Route::get('/courses/{id}/', 'CoursesController@details');
    Route::get('/courses/{id}/enroll', 'CoursesController@enroll');
    Route::get('/courses/{id}/report', 'CoursesController@report');
    Route::get('/courses/{id}/blocked', 'CoursesController@blocked');
    Route::get('/courses/{id}/edit', 'CoursesController@editView');
    Route::get('/courses/{id}/start', 'CoursesController@start');
    Route::get('/courses/{id}/stop', 'CoursesController@stop');
    Route::post('/courses/{id}/edit', 'CoursesController@edit');
    Route::get('/courses/{id}/assessments', 'CoursesController@assessments');
    Route::get('/courses/{id}/export', 'CoursesController@export');
    Route::get('/courses/{id}/export-md', 'CoursesController@exportMarkdown');
    Route::get('/courses/{id}/chapter', 'CoursesController@createChapterView');
    Route::post('/courses/{id}/chapter', 'CoursesController@createChapter');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/edit', 'CoursesController@editChapterView');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/upper', 'CoursesController@makeChapterUpper');
    Route::get('/courses/{course_id}/chapters/{chapter_id}/lower', 'CoursesController@makeChapterLower');
    Route::post('/courses/{course_id}/chapters/{chapter_id}/default', 'CoursesController@setDefaultChapter');
    Route::post('/courses/{course_id}/chapters/{chapter_id}/edit', 'CoursesController@editChapter');


    Route::get('/courses/{id}/create', 'LessonsController@createView');
    Route::post('/courses/{id}/create', 'LessonsController@create');


    Route::get('/courses/{course_id}/lessons/{id}/edit', 'LessonsController@editView');
    Route::post('/courses/{course_id}/lessons/{id}/edit', 'LessonsController@edit');
    Route::post('/courses/{course_id}/lessons/{id}/deadline', 'LessonsController@makeDeadline');
    Route::post('/courses/{course_id}/lessons/{id}/early-access', 'LessonsController@buyEarlyAccess');
    Route::get('/courses/{course_id}/lessons/{id}/export', 'LessonsController@export');
    Route::get('/courses/{course_id}/lessons/{id}/export-md', 'LessonsController@exportMarkdown');
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
    Route::post('/courses/{course_id}/tasks/{id}/deadline', 'TasksController@makeDeadline');
    Route::get('/courses/{course_id}/tasks/{id}/recheck-all', 'TasksController@recheckAllSolutions');
    Route::post('/courses/{course_id}/tasks/{id}/ai-summary', 'TasksController@aiTaskSummary');


    Route::post('/courses/{course_id}/tasks/{id}/edit', 'TasksController@edit');
    Route::post('/courses/{course_id}/tasks/{id}/solution', 'TasksController@postSolution');
    Route::get('/courses/{course_id}/tasks/{id}/solution/{solution_id}/recheck', 'TasksController@askForRecheck');
    Route::post('/courses/{course_id}/tasks/{id}/solution/{solution_id}/skip-review', 'TasksController@skipSolutionReview');
    Route::post('/courses/{course_id}/tasks/{id}/student/{student_id}/skip-review', 'TasksController@skipStudentReviews');
    Route::post('/courses/{course_id}/tasks/{id}/solution/{solution_id}/deadline-penalty', 'TasksController@payDeadlinePenalty');
    Route::post('/courses/{course_id}/tasks/{id}/solution/{solution_id}/xp-booster', 'TasksController@useXpBooster');
    Route::post('/courses/{course_id}/tasks/{id}/geekpaste-extra-attempt', 'TasksController@buyGeekPasteExtraAttempt');
    Route::get('/courses/{course_id}/tasks/{id}/phantom', 'TasksController@phantomSolution');
    Route::get('/courses/{course_id}/tasks/{id}/student/{student_id}', 'TasksController@reviewSolutions');
    Route::get('/courses/{course_id}/tasks/{id}/block/{student_id}', 'TasksController@blockStudent');
    Route::get('/courses/{course_id}/tasks/{id}/unblock/{student_id}', 'TasksController@unblockStudent');
    Route::post('/courses/{course_id}/solution/{id}', 'TasksController@estimateSolution');
    Route::get('/invite', 'CoursesController@invite');

    Route::get('/community', 'ProfileController@index');
    Route::get('/profile/{id?}', 'ProfileController@details');


    Route::get('/profile/{id}/edit', 'ProfileController@editView');
    Route::post('/profile/{id}/edit', 'ProfileController@edit');
    Route::get('/profile/{id}/telegram-link', 'ProfileController@telegramLink');
    Route::post('/profile/{id}/telegram-unlink', 'ProfileController@telegramUnlink');
    Route::post('/profile/{id}/custom-title', 'ProfileController@buyCustomTitle');
    Route::post('/profile/{id}/avatar-frame', 'ProfileController@buyAvatarFrame');
    Route::post('/profile/{id}/course', 'ProfileController@course');
    Route::get('/profile/delete-course/{id}', 'ProfileController@deleteCourse');
    Route::get('/profile/{user_id}/delete-course/{course_id}', 'ProfileController@deleteCurrentCourse');
    Route::post('/profile/{user_id}/money', 'ProfileController@addMoney');

    Route::get('/testmail', function () {
        $user = \App\User::findOrFail(1);
        $when = \Carbon\Carbon::now()->addSeconds(1);
        $user->notify((new \App\Notifications\NewSolution())->delay($when));
    });

});


Route::get('media/{dir}/{name}', 'MediaController@index');
