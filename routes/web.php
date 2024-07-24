<?php

use App\Http\Controllers\Media\FileController;
use App\Http\Controllers\Media\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user())->name('user');
    Route::post('/upload', [UploadController::class, 'upload'])->name('upload');
    Route::post('/upload/{identifier}/pause', [UploadController::class, 'pause'])->name('upload.pause');
    Route::delete('/upload/{identifier}', [UploadController::class, 'delete'])->name('upload.delete');

    // Merge the following routes into a single route group
    Route::get('/uploads', [UploadController::class, 'index']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);

    // Storage
    Route::get('/storage/{path}', function ($path) {
         if (!auth()->user()->files()->where('path', $path)->exists()) {
            abort(403);
         }
        return response()->file(storage_path('app/' . $path));
    })->where('path', '.*');
});
