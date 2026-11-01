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


Route::post('/geekpaste', 'GeekPasteAPI@submitSolution');
Route::post('/geekpaste/integrity', 'GeekPasteAPI@syncIntegrity');
Route::get('/geekpaste/users/{id}', 'GeekPasteAPI@userProfile');
Route::post('/geekpaste/recheck/status', 'GeekPasteAPI@recheckStatus');
Route::post('/geekpaste/recheck', 'GeekPasteAPI@requestRecheck');
