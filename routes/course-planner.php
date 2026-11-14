<?php

use App\Http\Controllers\CoursePlanner\PlannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('insider/courses/{course_id}/planner')->middleware(['web', 'auth', 'verified'])
    ->where(['course_id' => '[0-9]+'])->group(function () {
        Route::get('/', [PlannerController::class, 'page'])->name('courses.planner');
        Route::get('/api/state', [PlannerController::class, 'state']);
        Route::get('/api/summary/details', [PlannerController::class, 'details']);
        Route::post('/api/calendar/preview', [PlannerController::class, 'preview']);
        Route::post('/api/commands/{operation}', [PlannerController::class, 'command'])->where('operation', '[a-z_]+');
        Route::get('/api/providers/{provider_key}/resources', [PlannerController::class, 'catalog'])->where('provider_key', '[a-zA-Z0-9_-]+');
        Route::get('/api/entries/{link_id}', [PlannerController::class, 'entry'])->whereNumber('link_id');
    });
