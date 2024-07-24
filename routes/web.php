<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user());

    Route::post('/upload', [UploadController::class, 'upload']);
    Route::post('/upload/{identifier}/pause', [UploadController::class, 'pause']);
    Route::delete('/upload/{identifier}', [UploadController::class, 'delete']);

    // Merge the following routes into a single route group
    Route::get('/uploads', [UploadController::class, 'index']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);

});
