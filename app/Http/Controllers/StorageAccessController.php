<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class StorageAccessController extends Controller
{

    public function __invoke(Request $request, string $path)
    {
        $fileRecord = auth()->user()->files()->where('path', $path)->first();

        if (!$fileRecord) {
            abort(403);
        }

        $file = storage_path('app/' . $fileRecord->path);

        return response()->file($file);
    }
}
