<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\File;
use App\Models\Upload;
use App\Models\User;
use Carbon\Carbon;
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

        $collection = collect($files)->map(fn($file) => [
            ...$file->except('created_at', 'updated_at'),
            'created_at' => Carbon::parse($file->created_at)->toDateTimeString(),
            'updated_at' => Carbon::parse($file->updated_at)->diffForHumans()
        ]);

        return response()->json($collection->toArray());
    }

    public function upload(ChunkUploadRequest $request)
    {
        $user = $request->user();
        $fileName = $request->input('fileName');
        $identifier = $request->input('identifier');
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $chunksPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        $currentChunk->storeAs($chunksPath, "{$identifier}/{$fileName}.{$chunkIndex}");

        $upload = $user->uploads()->updateOrCreate(['identifier' => $identifier], [
            'file_name' => $fileName,
            'file_path' => $chunksPath,
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => $chunkIndex + 1,
        ]);

        $upload->chunks()->updateOrCreate(['index' => $chunkIndex], [
            'path' => "{$chunksPath}/{$identifier}/{$fileName}.{$chunkIndex}",
            'size' => $currentChunk->getSize()
        ]);

        $uploadedChunks = $upload->uploaded_chunks;

        if ($uploadedChunks < $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $this->calculateProgress($uploadedChunks, $totalChunks),
                'identifier' => $identifier
            ], Response::HTTP_OK);
        }

        // All chunks have been uploaded
        // ______________________________________
        try {
            $this->assembleChunks($identifier, $fileName, $totalChunks);
            $file = $this->createFileRecord($user, $fileName);
            $this->deleteChunksAndUpload($upload);

            return response([
                'status' => 'completed',
                'progress' => 100,
                'identifier' => $identifier,
                'file' => $file
            ], Response::HTTP_CREATED);

        } catch (Exception $e) {

            $upload->update(['status' => 'failed']);

            return response([
                'status' => 'failed',
                'reason' => $e->getMessage(),
                'identifier' => $identifier,
                'progress' => $this->calculateProgress($uploadedChunks, $totalChunks),
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
        $sourcePath = "$storagePrefix/{$this->chunksDir}/$identifier/$fileName";
        $destinationPath = "$storagePrefix/{$this->uploadsDir}/$fileName";

        if (!Storage::directoryExists("$storagePrefix/{$this->uploadsDir}")) {
            Storage::makeDirectory($storagePrefix . '/' . $this->uploadsDir);
        }

        $destinationFile = fopen(storage_path("app/$destinationPath"), 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
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

        $this->deleteChunksAndUpload($upload);

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
