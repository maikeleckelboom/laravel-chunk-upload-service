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

    Route::post('/upload', [FileController::class, 'upload']);
    Route::post('/upload/{identifier}/abort', [FileController::class, 'abort']);
    Route::post('/upload/{identifier}/pause', [FileController::class, 'pause']);
    Route::get('/uploads', [UploadController::class, 'index']);

    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);

});
