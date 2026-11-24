<?php

use App\Http\Controllers\MaterialHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/material-resources/{id}', [MaterialHistoryController::class, 'resource']);
    Route::get('/insider/material-history/{id}', [MaterialHistoryController::class, 'attempt']);
    Route::get('/insider/material-code-history/{id}', [MaterialHistoryController::class, 'codeAttempt']);
});
