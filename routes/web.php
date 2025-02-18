<?php

use App\Http\Controllers\Media\FileController;
use App\Http\Controllers\Media\UploadController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user())->name('user');
    Route::get('/uploads', [UploadController::class, 'index']);
    Route::post('/upload', [UploadController::class, 'store'])->name('upload');
    Route::post('/upload/{identifier}/pause', [UploadController::class, 'pause'])->name('upload.pause');
    Route::delete('/upload/{identifier}', [UploadController::class, 'delete'])->name('upload.delete');
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);
    Route::get('/storage/{path}', StorageController::class)->where('path', '.*')->name('storage.path');
});
