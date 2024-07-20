<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user());
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/upload', [FileController::class, 'upload']);
    Route::post('/upload/{identifier}/abort', [FileController::class, 'abort']);
    Route::post('/upload/{identifier}/pause', [FileController::class, 'pause']);
    Route::get('/upload/{identifier}/status', [FileController::class, 'status']);
});
