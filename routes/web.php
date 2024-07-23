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

    Route::post('/upload/v2', [UploadController::class, 'upload']);

    Route::get('/uploads', [UploadController::class, 'index']);
    Route::delete('/upload/{identifier}', [UploadController::class, 'forceDelete']);
    Route::post('/upload/{identifier}/pause', [UploadController::class, 'pause']);

    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);

});
