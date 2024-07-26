<?php

namespace App\Http\Controllers;

use App\Models\Media\File;
use Illuminate\Http\Request;
use Intervention\Image\Laravel\Facades\Image;

class StorageController extends Controller
{

    public function __invoke(Request $request, string $path)
    {
        $file = $this->findFileByPath($path);
        if (!$file) {
            abort(403);
        }

        $storagePath = storage_path("app/{$file->path}");


//            $image = Image::read($storagePath);
//            $image->scale(600);
//            $thumbnailPath = storage_path("app/{$this->withoutExtension($file->path)}_preview.{$file->extension}");
//            $image->save($thumbnailPath);
//            return response()->file($thumbnailPath);

        return response()->file($storagePath);
    }


    public function findFileByPath(string $path): File|null
    {
        return auth()->user()->files()->where('path', $path)->first();
    }

    private function withoutExtension(string $path): string
    {
        return substr($path, 0, strrpos($path, '.'));
    }

    private function matchFileType(string $mime_type, string $extension): string
    {
        if (str_contains($mime_type, 'image')) {
            return 'image';
        }

        if (str_contains($mime_type, 'video')) {
            return 'video';
        }

        if (str_contains($mime_type, 'audio')) {
            return 'audio';
        }

        return $extension;

    }
}
