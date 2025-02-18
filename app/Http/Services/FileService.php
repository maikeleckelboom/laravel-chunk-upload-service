<?php

namespace App\Http\Services;

use App\Models\Media\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function create(User $user, string $path): File|Model
    {
        return $user->files()->firstOrCreate(['path' => $path], [
            'name' => $fileName = pathinfo($path, PATHINFO_BASENAME),
            'size' => Storage::size($path),
            'mime_type' => Storage::mimeType($path),
            'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'path' => $path
        ]);
    }
    
    public function move(File $file, string $directory): void
    {
        $newPath = "{$directory}/{$file->name}";
        Storage::move($file->path, $newPath);
        $file->update(['path' => $newPath]);
    }
    
    public function delete(int $id, ?User $user = null): void
    {
        $user ??= Auth::user();
        $file = $user->files()->findOrFail($id);
        if (Storage::delete($file->path)) {
            $file->delete();
        }
    }
}
