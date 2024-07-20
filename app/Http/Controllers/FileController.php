<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\File;
use App\Models\Upload;
use App\Models\User;
use DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;


class FileController extends Controller
{

    private string $chunksDir = 'temp/chunks';
    private string $uploadsDir = 'uploads';

    public function index(Request $request)
    {
        $files = Auth::user()->files()->get();
        return response()->json($files);
    }

    public function upload(ChunkUploadRequest $request)
    {
        $user = $request->user();
        $fileName = $request->input('fileName');
        $identifier = $request->input('identifier');
        $chunkNumber = (int)$request->input('chunkNumber');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $chunksPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        $currentChunk->storeAs($chunksPath, "{$identifier}/{$fileName}.{$chunkNumber}");

        $upload = $user->uploads()->updateOrCreate(['identifier' => $identifier], [
            'file_name' => $fileName,
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => $chunkNumber,
        ]);

        $upload->chunks()->updateOrCreate(['number' => $chunkNumber], [
            'path' => "{$chunksPath}/{$identifier}/{$fileName}.{$chunkNumber}",
            'size' => $currentChunk->getSize()
        ]);

        if ($chunkNumber !== $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $this->calculateProgress($chunkNumber, $totalChunks),
                'identifier' => $identifier,
            ], Response::HTTP_OK);
        }

        // All chunks have been uploaded
        // Assemble chunks and create file record
        // ______________________________________
        try {
            $this->assembleChunks($identifier, $fileName, $totalChunks);
            $file = $this->createFileRecord($user, $fileName);
            $this->deleteChunksAndUpload($upload);

            return response([
                'status' => 'completed',
                'identifier' => $identifier,
                'progress' => $this->calculateProgress($chunkNumber, $totalChunks),
                'file' => $file
            ], Response::HTTP_CREATED);

        } catch (Exception $e) {

            $upload->update(['status' => 'failed']);

            return response([
                'status' => 'failed',
                'reason' => $e->getMessage(),
                'identifier' => $identifier,
                'progress' => $this->calculateProgress($chunkNumber, $totalChunks),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param int $uploadedChunks
     * @param int $totalChunks
     * @return float
     */
    private function calculateProgress(int $uploadedChunks, int $totalChunks): float
    {
        return $uploadedChunks / $totalChunks * 100;
    }

    /**
     * @throws Exception
     */
    private function assembleChunks(string $identifier, string $fileName, int $totalChunks)
    {
        $upload = Auth::user()->uploads()->where('identifier', $identifier)->firstOrFail();

        if ($upload->chunks()->count() !== $totalChunks) {
            throw new Exception("Missing chunks for upload $identifier");
        }

        $storagePrefix = Auth::user()->getStoragePrefix();
        $sourcePath = "$storagePrefix/{$this->chunksDir}/$identifier/{$fileName}";
        $destination = "{$storagePrefix}/{$this->uploadsDir}/$fileName";

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

    private function createFileRecord(User $user, string $fileName): File
    {
        $path = $user->getStoragePrefix() . '/' . $this->uploadsDir . '/' . $fileName;

        return $user->files()->updateOrCreate(['path' => $path], [
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'size' => Storage::size($path),
            'mime_type' => Storage::mimeType($path),
            'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'path' => $path
        ]);
    }

    /**
     * @param Upload $upload
     * @return void
     */
    private function deleteChunksAndUpload(Upload $upload): void
    {
        DB::transaction(function () use ($upload) {
            $upload->update(['status' => 'completed']);
            $upload->chunks()->delete();
            $upload->delete();
        });
    }

    public function abort(Request $request, string $identifier)
    {
        $user = $request->user();
        $upload = $user->uploads()->where('identifier', $identifier)->first();

        if (!$upload) {
            return response(null, Response::HTTP_NO_CONTENT);
        }

        Storage::deleteDirectory(
            $user->getStoragePrefix() . '/' . $this->chunksDir . '/' . $identifier
        );

        foreach ($upload->chunks as $chunk) {
            $chunk->delete();
        }

        $upload->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function pause(Request $request, string $identifier)
    {
        $user = $request->user();
        $upload = $user->uploads()->where('identifier', $identifier)->first();

        if (!$upload) {
            return response(null, Response::HTTP_NO_CONTENT);
        }

        $upload->update(['status' => 'paused']);

        return response(null, Response::HTTP_NO_CONTENT);
    }

}
