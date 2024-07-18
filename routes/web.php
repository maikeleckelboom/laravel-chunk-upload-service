<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user());
    Route::post('/upload', [FileController::class, 'upload']);
    Route::get('/upload-status/{filename}', [FileController::class, 'uploadStatus']);
});
