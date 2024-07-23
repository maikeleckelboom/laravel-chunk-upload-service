<?php

namespace App\Http\Services;

use App\Data\UploadData;
use App\Models\File;
use App\Models\Upload;
use App\Models\User;
use App\UploadStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadService
{
    private string $chunksDirectory = 'temp/chunks';
    private string $filesDirectory = 'files';

    public function getUploadQueue(User $user): Collection
    {
        return $user->uploads()->get()->map(fn(Upload $upload) => [
            'identifier' => $upload->identifier,
            'fileName' => $upload->file_name,
            'status' => $upload->status,
            'progress' => ($upload->uploaded_chunks / $upload->total_chunks) * 100
        ]);
    }

    public function uploadChunk(User $user, UploadData $data): Upload|false
    {
        $path = "{$user->getStoragePrefix()}/{$this->chunksDirectory}/{$data->identifier}";

        if (!$this->storeChunk($data, $path)) {
            return false;
        }

        return $this->createChunkWithUpload($user, $data, $path);
    }

    private function storeChunk(UploadData $data, string $path): false|string
    {
        return $data->currentChunk->storeAs($path, "{$data->fileName}.{$data->chunkIndex}");
    }

    private function createChunkWithUpload(User $user, UploadData $data, string $path)
    {
        return DB::transaction(function () use ($user, $data, $path) {
            $upload = $this->createUpload($user, $data, $path);
            $this->createChunk($upload, $data);
            return $upload;
        });
    }

    public function createUpload(User $user, UploadData $data, string $uploadChunkPath): Upload
    {
        return $user->uploads()->updateOrCreate(['identifier' => $data->identifier], [
            'path' => $uploadChunkPath,
            'file_name' => $data->fileName,
            'total_chunks' => $data->totalChunks,
            'uploaded_chunks' => $data->chunkIndex + 1,
            'status' => UploadStatus::PENDING
        ]);
    }

    public function createChunk(Upload $upload, UploadData $data)
    {
        return $upload->chunks()->updateOrCreate(['index' => $data->chunkIndex], [
            'path' => "{$upload->path}/{$data->fileName}.{$data->chunkIndex}",
            'size' => $data->currentChunk->getSize()
        ]);
    }

    public function hasUploadedAllChunks(Upload $upload): bool
    {
        return $upload->uploaded_chunks === $upload->total_chunks;
    }

    public function assembleChunks(Upload $upload): bool
    {
        $uploadsDirectory = $this->prepareFilesDirectory($upload->user);

        $resource = fopen(storage_path("app/{$uploadsDirectory}/$upload->file_name"), 'wb');

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunk = fopen(storage_path("app/{$upload->path}/{$upload->file_name}.$i"), 'rb');
            stream_copy_to_stream($chunk, $resource);
            fclose($chunk);
        }

        return fclose($resource);
    }


    public function createFileForUpload(Upload $upload): File
    {
        $path = "{$upload->user->getStoragePrefix()}/{$this->filesDirectory}/{$upload->file_name}";
        return (new FileService())->create($upload->user, $path);
    }

    public function pause(Upload $upload): bool
    {
        return $upload->update(['status' => UploadStatus::PAUSED]);
    }

    public function find(User $user, string $identifier): Upload
    {
        return $user->uploads()->where('identifier', $identifier)->firstOrFail();
    }

    public function delete(Upload $upload)
    {
        return DB::transaction(function () use ($upload) {
            $chunks = $upload->chunks()->get();
            $chunks->each(fn($chunk) => Storage::delete($chunk->path) && $chunk->delete());
            Storage::deleteDirectory($upload->path);
            $upload->delete() && $this->cleanupChunksDirectory($upload->user);
        });
    }

    private function prepareFilesDirectory(User $user): string
    {
        $uploadsDirectory = "{$user->getStoragePrefix()}/{$this->filesDirectory}";

        if (!Storage::directoryExists($uploadsDirectory)) {
            Storage::makeDirectory($uploadsDirectory);
        }

        return $uploadsDirectory;
    }

    private function cleanupChunksDirectory(User $user): void
    {
        [$chunksRootDirectory] = explode('/', $this->chunksDirectory);
        $chunksRootDirectory = "{$user->getStoragePrefix()}/{$chunksRootDirectory}";

        if (Storage::directoryExists($chunksRootDirectory) && Storage::allFiles($chunksRootDirectory) === []) {
            Storage::deleteDirectory($chunksRootDirectory);
        }
    }
}
