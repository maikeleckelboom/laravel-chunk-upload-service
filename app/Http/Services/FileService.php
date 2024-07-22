<?php

namespace App\Http\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function create(User $user, string $path): File
    {
        $fileName = pathinfo($path, PATHINFO_BASENAME);
        return $user->files()->firstOrCreate(['path' => $path], [
            'name' => $fileName,
            'size' => Storage::size($path),
            'mime_type' => Storage::mimeType($path),
            'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'path' => $path
        ]);
    }

    public function delete(int $id, ?User $user = null): void
    {
        $user ??= Auth::user();
        $file = $user->files()->findOrFail($id);
        if (Storage::delete($file->path)) {
            $file->delete();
        }
    }

    public function deleteAll( ?User $user = null): void
    {
        $user ??= Auth::user();
        $user->files()->get()->each(fn($file) => $this->delete($file->id, $user));
    }
}
