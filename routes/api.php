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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


Route::prefix('games')->group(function(){
  Route::get('{id}/info', 'GamesAPIController@info');
  Route::post('{id}/update', 'GamesAPIController@update');
});

Route::post('/vk/feedback', 'BotController@saveFeedback');
