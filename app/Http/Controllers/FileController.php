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

        $collection = collect($files)
            ->map(fn($file) => [
                ...collect($file)->except('created_at', 'updated_at')->toArray(),
                'created_at' => Carbon::parse($file->created_at)->toDateTimeString(),
                'updated_at' => Carbon::parse($file->updated_at)->diffForHumans()
            ])
            ->sortByDesc('updated_at')
            ->values();


        return response()->json($collection->toArray());
    }

    public function upload(ChunkUploadRequest $request)
    {
        $fileName = $request->input('fileName');
        $identifier = $request->input('identifier');
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $user = $request->user();
        $chunksPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        $currentChunk->storeAs($chunksPath, "{$identifier}/{$fileName}.{$chunkIndex}");
        $uploadedChunks = $chunkIndex + 1;


        $upload = $user->uploads()->updateOrCreate(['identifier' => $identifier], [
            'file_name' => $fileName,
            'file_path' => "{$chunksPath}/{$identifier}/$fileName",
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => $uploadedChunks
        ]);


        $upload->chunks()->updateOrCreate(['index' => $chunkIndex], [
            'path' => "{$chunksPath}/{$identifier}/{$fileName}.{$chunkIndex}",
            'size' => $currentChunk->getSize()
        ]);

        $progress = $this->calculateProgress($uploadedChunks, $totalChunks);

        if ($uploadedChunks < $totalChunks) {
            logger()->info("Progress: $progress%");
            logger()->info("Chunk $uploadedChunks of $totalChunks uploaded for $identifier");
            return response([
                'status' => 'pending',
                'progress' => $progress,
                'identifier' => $identifier
            ], Response::HTTP_OK);
        }

        // All chunks have been uploaded
        // ______________________________________
        try {
            $this->assembleChunks($identifier, $fileName, $totalChunks);

            $file = $this->createFileRecord($user, $fileName);

            $this->deleteChunksAndUpload($upload);

            logger()->info("Upload $identifier completed", ['file' => $file->path]);

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
                'progress' => $progress
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
        return ($uploadedChunks / $totalChunks) * 100;
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
        $destinationPath = "$storagePrefix/{$this->uploadsDir}/$fileName";

        if (!Storage::directoryExists("$storagePrefix/{$this->uploadsDir}")) {
            Storage::makeDirectory("$storagePrefix/{$this->uploadsDir}");
        }

        $destinationFile = fopen(storage_path("app/$destinationPath"), 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = fopen(storage_path("app/{$upload->file_path}.$i"), 'rb');
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

    private function deleteChunksAndUpload(Upload $upload, bool $forceDelete = false): void
    {
        $upload->update(['status' => 'completed']);

        DB::transaction(function () use ($forceDelete, $upload) {
            if ($forceDelete) {
                $upload->chunks()->forceDelete();
                $upload->forceDelete();
                return;
            }
            $upload->chunks()->delete();
            $upload->delete();
        });
    }

    public function delete(Request $request, int $id)
    {
        $file = Auth::user()->files()->findOrFail($id);

        Storage::delete($file->path);

        $file->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function abort(Request $request, string $identifier)
    {
        $user = $request->user();
        $upload = $user->uploads()->where('identifier', $identifier)->first();

        if (!$upload) {
            return response(null, Response::HTTP_NO_CONTENT);
        }

        Storage::deleteDirectory($upload->file_path);

        $this->deleteChunksAndUpload($upload, true);

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

    public function status(Request $request, string $identifier)
    {
        $user = $request->user();
        $upload = $user->uploads()->where('identifier', $identifier)->first();

        if (!$upload) {
            return response(null, Response::HTTP_NO_CONTENT);
        }

        return response([
            'status' => $upload->status,
            'progress' => $this->calculateProgress($upload->uploaded_chunks, $upload->total_chunks)
        ], Response::HTTP_OK);
    }
}
