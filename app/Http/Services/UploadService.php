<?php

namespace App\Http\Services;

use App\Data\UploadData;
use App\Enum\UploadStatus;
use App\Models\Media\File;
use App\Models\Media\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadService
{
    private string $chunksDirectory = 'temp/chunks';
    private string $filesDirectory = 'files';

    public function getAll(User $user): Collection
    {
        return $user->uploads()->get();
    }

    /**
     * @throws Throwable
     */
    public function uploadChunk(User $user, UploadData $data): Upload|false
    {
        $path = "{$user->getStoragePrefix()}/{$this->chunksDirectory}/{$data->identifier}";

        if (!$this->storeChunk($data, $path)) {
            return false;
        }

        return DB::transaction(function () use ($user, $data, $path) {
            $upload = $this->createUpload($user, $data, $path);
            $this->createChunk($upload, $data);
            return $upload;
        });
    }

    private function storeChunk(UploadData $data, string $path): false|string
    {
        return $data->currentChunk->storeAs($path, "{$data->fileName}.{$data->chunkIndex}");
    }

    public function createUpload(User $user, UploadData $data, string $path): Upload
    {
        return $user->uploads()->updateOrCreate(['identifier' => $data->identifier], [
            'path' => $path,
            'file_name' => $data->fileName,
            'file_type' => $data->fileType,
            'file_size' => $data->fileSize,
            'total_chunks' => $data->totalChunks,
            'uploaded_chunks' => $data->chunkIndex + 1,
            'status' => UploadStatus::PENDING
        ]);
    }

    public function createChunk(Upload $upload, UploadData $data)
    {
        return $upload->chunks()->updateOrCreate(['index' => $data->chunkIndex], [
            'path' => "{$upload->path}/{$upload->file_name}.{$data->chunkIndex}",
            'size' => $data->currentChunk->getSize()
        ]);
    }

    public function isReadyToAssemble(Upload $upload): bool
    {
        return $this->isAllChunksUploaded($upload) && $this->isTotalChunkSizeEqualToFileSize($upload);
    }

    public function isAllChunksUploaded(Upload $upload): bool
    {
        return $upload->uploaded_chunks === $upload->total_chunks;
    }

    public function isTotalChunkSizeEqualToFileSize(Upload $upload): bool
    {
        $totalSize = $upload->chunks()->sum('size');
        return (int)$totalSize === (int)$upload->file_size;
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

    private function prepareFilesDirectory(User $user): string
    {
        $uploadsDirectory = "{$user->getStoragePrefix()}/{$this->filesDirectory}";

        if (!Storage::directoryExists($uploadsDirectory)) {
            Storage::makeDirectory($uploadsDirectory);
        }

        return $uploadsDirectory;
    }

    public function createFile(Upload $upload): File
    {
        $filesDirectory = "{$upload->user->getStoragePrefix()}/{$this->filesDirectory}";
        $file = (new FileService())->create($upload->user, "{$filesDirectory}/{$upload->file_name}");
        $this->moveFile($file, "{$filesDirectory}/{$file->id}/{$upload->file_name}");
        $this->cleanupAndDelete($upload);
        return $file;
    }

    public function moveFile(Model|File $file, string $path): void
    {
        Storage::move($file->path, $path);
        $file->update(['path' => $path]);
    }

    public function cleanupAndDelete(Upload $upload): void
    {
        $upload->chunks()->get()->each(fn($chunk) => Storage::delete($chunk->path) && $chunk->delete());
        Storage::deleteDirectory($upload->path);
        $upload->delete() && $this->cleanupChunksDirectory($upload->user);
    }

    private function cleanupChunksDirectory(User $user): void
    {
        [$chunksRootDirectory] = explode('/', $this->chunksDirectory);
        $chunksRootDirectory = "{$user->getStoragePrefix()}/{$chunksRootDirectory}";

        if (Storage::directoryExists($chunksRootDirectory) && Storage::allFiles($chunksRootDirectory) === []) {
            Storage::deleteDirectory($chunksRootDirectory);
        }
    }


    public function pause(Upload $upload): bool
    {
        return $upload->update(['status' => UploadStatus::QUEUED]);
    }

    public function find(User $user, string $identifier): Upload|null
    {
        return $user->uploads()->where('identifier', $identifier)->first();
    }
}
