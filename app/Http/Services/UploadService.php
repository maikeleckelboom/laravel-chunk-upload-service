<?php

namespace App\Http\Services;

use App\Data\UploadData;
use App\Models\Upload;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class UploadService
{
    private string $chunksDir = 'temp/chunks';
    private string $uploadsDir = 'uploads';

    public function upload(User $user, UploadData $data): array
    {
        $chunksPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        $data->currentChunk->storeAs($chunksPath, "{$data->identifier}/{$data->fileName}.{$data->chunkIndex}");
        $uploadedChunks = $data->chunkIndex + 1;

        $upload = $user->uploads()->updateOrCreate(['identifier' => $data->identifier], [
            'path' => "{$chunksPath}/{$data->identifier}/{$data->fileName}",
            'file_name' => $data->fileName,
            'total_chunks' => $data->totalChunks,
            'uploaded_chunks' => $uploadedChunks
        ]);

        $upload->chunks()->updateOrCreate(['index' => $data->chunkIndex], [
            'path' => "{$chunksPath}/{$data->identifier}/{$data->fileName}.{$data->chunkIndex}",
            'size' => $data->currentChunk->getSize()
        ]);

        $progress = ($uploadedChunks / $data->totalChunks) * 100;

        if ($uploadedChunks < $data->totalChunks) {
            return [
                'status' => 'pending',
                'progress' => $progress,
                'identifier' => $data->identifier,
                'code'=> Response::HTTP_OK
            ];
        }

        $this->assembleChunks($data->identifier, $data->fileName, $data->totalChunks);

        return [
            'status' => 'completed',
            'progress' => 100,
            'identifier' => $data->identifier,
            'code'=> Response::HTTP_OK
        ];
    }

    public function assembleChunks(string $identifier, string $fileName, int $totalChunks)
    {
        // Method logic moved from UploadController
    }

    public function softDelete(Upload $upload): void
    {
        // Method logic moved from UploadController
    }

    public function forceDelete(Upload $upload): void
    {
        // Method logic moved from UploadController
    }

    public function isSameFileName(): bool
    {
        return false;
    }

    public function isSameSize(): bool
    {
        return false;
    }
}
