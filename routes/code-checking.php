<?php

use App\Http\Controllers\CodeCheckingController;
use App\Http\Controllers\TaskSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->prefix('insider')->where([
    'course' => '[0-9]+', 'task' => '[0-9]+',
    'submission' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    'run' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    'test' => '[a-f0-9]{64}',
])->group(function () {
    Route::get('/settings/code-executor/legacy-exports', [TaskSubmissionController::class, 'legacyExports']);
    Route::get('/settings/code-executor/legacy-artifacts/{artifact}', [TaskSubmissionController::class, 'archiveArtifact'])->where('artifact','[a-f0-9]{64}');
    Route::get('/settings/code-executor/legacy-exports/{export}/files/{file}', [TaskSubmissionController::class, 'exportFile'])->where(['export'=>'[a-f0-9]{64}','file'=>'[a-z0-9.-]+']);
    Route::get('/settings/code-executor/status', [CodeCheckingController::class, 'executorStatus']);
    Route::get('/settings/code-executor/login', [CodeCheckingController::class, 'executorLogin'])->name('code-executor.login');
    $base = '/courses/{course}/tasks/{task}';
    Route::get($base.'/checker/legacy', [TaskSubmissionController::class, 'legacySettings']);
    Route::get($base.'/checker/fixtures/{artifact}', [TaskSubmissionController::class, 'fixture'])->where('artifact', '[a-f0-9]{64}')->name('code-fixtures.download');
    Route::match(['get', 'post'], $base.'/checker/files', [TaskSubmissionController::class, 'taskFiles']);
    Route::post($base.'/checker/files/{attachment}/replace', [TaskSubmissionController::class, 'replaceTaskFile']);
    Route::match(['get', 'delete'], $base.'/checker/files/{attachment}', [TaskSubmissionController::class, 'taskFile'])->name('code-task-files.download');
    Route::match(['get', 'put', 'delete'], $base.'/code-draft', [TaskSubmissionController::class, 'draft']);
    Route::get($base.'/code-draft/files/{file}', [TaskSubmissionController::class, 'draftFile'])->whereNumber('file')->name('code-drafts.file');
    Route::post($base.'/code-submissions/recheck-latest', [TaskSubmissionController::class, 'recheckLatest']);
    Route::get($base.'/checker', [CodeCheckingController::class, 'settings']);
    Route::match(['get', 'put'], $base.'/checker/private', [CodeCheckingController::class, 'privateSettings']);
    Route::post($base.'/checker/fixtures', [CodeCheckingController::class, 'uploadFixture']);
    Route::post($base.'/checker/extra-attempt', [CodeCheckingController::class, 'extraAttempt']);
    Route::match(['get', 'post'], $base.'/code-submissions', [CodeCheckingController::class, 'index']);
    Route::get('/code-submissions/{submission}', [CodeCheckingController::class, 'show'])->name('code-submissions.show');
    Route::post('/code-submissions/{submission}/manual-review', [TaskSubmissionController::class, 'manualReview']);
    Route::post('/code-submissions/{submission}/review-request', [TaskSubmissionController::class, 'reviewRequest']);
    Route::put('/code-submissions/{submission}/flags', [TaskSubmissionController::class, 'flags']);
    Route::get('/code-submissions/{submission}/compare', [TaskSubmissionController::class, 'compare']);
    Route::put('/code-submissions/{submission}/grade', [TaskSubmissionController::class, 'grade']);
    Route::put('/code-submissions/{submission}/integrity/review', [TaskSubmissionController::class, 'reviewIntegrity']);
    Route::get('/code-submissions/{submission}/task-files/{file}', [TaskSubmissionController::class, 'snapshotFile'])->whereNumber('file');
    Route::get('/code-submissions/{submission}/repository/archive', [TaskSubmissionController::class, 'repositoryArchive']);
    Route::get('/code-submissions/{submission}/legacy-source', [TaskSubmissionController::class, 'legacySource']);
    Route::get('/code-submissions/{submission}/runs/{run}/files/{file}', [TaskSubmissionController::class, 'runFile'])->whereNumber('file');
    Route::get('/code-submissions/{submission}/runs/{run}', [TaskSubmissionController::class, 'run']);
    Route::get('/code-submissions/{submission}/subscription', [CodeCheckingController::class, 'subscription']);
    Route::get('/code-submissions/{submission}/files/{file}', [CodeCheckingController::class, 'file'])->whereNumber('file')->name('code-submissions.file');
    Route::post('/code-submissions/{submission}/recheck', [CodeCheckingController::class, 'recheck']);
    Route::get('/code-test-reports/{run}', [CodeCheckingController::class, 'report']);
    Route::post('/code-test-reports/{run}/tests/{test}/purchase', [CodeCheckingController::class, 'purchase']);
    Route::get('/code-test-reports/{run}/tests/{test}/{field}', [CodeCheckingController::class, 'download'])->name('code-reports.download');
});

Route::middleware('web')->group(function () {
    Route::get('/legacy/geekpaste/{legacy}/{action?}', [\App\Http\Controllers\LegacySubmissionController::class,'resolve'])->where(['legacy'=>'[A-Za-z0-9_-]+','action'=>'view|raw|zip|image|xlsx']);
    Route::get('/shared/code-submissions/{submission}/files/{file}', [\App\Http\Controllers\LegacySubmissionController::class,'file'])->whereNumber('file');
    Route::get('/shared/code-submissions/{submission}/{action?}', [\App\Http\Controllers\LegacySubmissionController::class,'show'])->where('action','view|raw|zip|image|xlsx');
});
