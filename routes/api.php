<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/geekexam/wifi-disconnect', 'GeekExamWifiAlertController@store');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


// Separate budget from the legacy 60/minute API limit: one check may emit
// dozens of progress events and upload multiple output artifacts.
Route::prefix('executor')->withoutMiddleware('throttle:60,1')->middleware('throttle:6000,1')->group(function () {
    Route::get('/executions/{execution}/input', 'CodeCheckerAPI@input');
    Route::post('/executions/{execution}/progress', 'CodeCheckerAPI@progress');
    Route::post('/executions/{execution}/result', 'CodeCheckerAPI@result');
    Route::post('/test-reports', 'CodeCheckerAPI@report');
    Route::post('/test-artifacts/status', 'CodeCheckerAPI@artifactStatus');
    Route::put('/test-artifacts/{artifact}', 'CodeCheckerAPI@uploadArtifact');
    Route::get('/artifacts/{artifact}', 'CodeCheckerAPI@artifact')->name('checker.artifact');
});
