<?php

use App\Http\Controllers\MaterialReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','admin'])->prefix('insider/material-review')->group(function () {
    Route::get('/',[MaterialReviewController::class,'index'])->name('material-review');
    Route::post('/drafts',[MaterialReviewController::class,'create']);
    Route::get('/drafts/{id}',[MaterialReviewController::class,'read']);
    Route::post('/drafts/{id}/{action}',[MaterialReviewController::class,'action']);
    Route::post('/preview',[MaterialReviewController::class,'preview']);
});
Route::middleware(['web','auth'])->get('/material-resources/{id}',[MaterialReviewController::class,'resource']);
Route::middleware(['web','auth'])->get('/insider/material-history/{id}',[MaterialReviewController::class,'attempt']);
Route::middleware(['web','auth'])->get('/insider/material-code-history/{id}',[MaterialReviewController::class,'codeAttempt']);
