<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;


class FileController extends Controller
{
    private string $chunksDir = 'temp/chunks';
    private string $uploadsDir = 'uploads';

    public function upload(ChunkUploadRequest $request)
    {
        $user = $request->user();
        $identifier = $request->input('identifier');
        $filename = $request->input('filename');
        $chunkNumber = (int)$request->input('chunkNumber');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $chunkPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        $currentChunk->storeAs($chunkPath, "{$identifier}/{$filename}.{$chunkNumber}");

        $upload = $user->uploads()->updateOrCreate([
            'identifier' => $identifier,
            'filename' => $filename
        ], [
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => $chunkNumber,
            'status' => 'pending'
        ]);

        $upload->chunks()->updateOrCreate(['chunk_number' => $chunkNumber], [
            'chunk' => $currentChunk,
            'chunk_size' => $currentChunk->getSize()
        ]);

        if ($chunkNumber !== $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $chunkNumber / $totalChunks * 100,
            ], Response::HTTP_OK);
        }

        try {
            $this->assembleChunks($identifier, $filename, $totalChunks);

            DB::transaction(function () use ($upload, $user, $filename) {
                $this->createFileRecord($user, $filename);
                $upload->chunks()->delete();
                $upload->delete();
            });

            return response([
                'status' => 'completed',
                'identifier' => $identifier,
                'filename' => $filename,
                'url' => Storage::url($user->getStoragePrefix() . '/' . $this->uploadsDir . '/' . $filename)
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response([
                'status' => 'error-assembling-chunks',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws Exception
     */
    private function assembleChunks(string $identifier, string $filename, int $totalChunks)
    {
        $upload = Auth::user()->uploads()->where('identifier', $identifier)->firstOrFail();

        if ($upload->chunks()->count() !== $totalChunks) {
            throw new Exception("Missing chunks for upload $identifier");
        }

        $storagePrefix = Auth::user()->getStoragePrefix();
        $sourcePath = "$storagePrefix/{$this->chunksDir}/$identifier/{$filename}";
        $destination = "{$storagePrefix}/{$this->uploadsDir}/$filename";

        if (!Storage::directoryExists($storagePrefix . '/' . $this->uploadsDir)) {
            Storage::makeDirectory($storagePrefix . '/' . $this->uploadsDir);
        }

        $destinationFile = fopen(storage_path("app/$destination"), 'wb');

        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunk = fopen(storage_path("app/$sourcePath.$i"), 'rb');
            stream_copy_to_stream($chunk, $destinationFile);
            fclose($chunk);
        }

        fclose($destinationFile);
    }

    /**
     * @param mixed $user
     * @param mixed $filename
     * @return void
     */
    public function createFileRecord(mixed $user, mixed $filename): void
    {
        $path = $user->getStoragePrefix() . '/' . $this->uploadsDir . '/' . $filename;
        $user->files()->updateOrCreate(['path' => $path], [
            'name' => pathinfo($filename, PATHINFO_FILENAME),
            'size' => Storage::size($path),
            'mime_type' => Storage::mimeType($path),
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'path' => $path
        ]);
    }
}
