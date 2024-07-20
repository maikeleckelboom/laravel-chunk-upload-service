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
            ->sortByDesc('updated_at')
            ->map(fn($file) => [
                ...collect($file)->except('created_at', 'updated_at')->toArray(),
                'created_at' => Carbon::parse($file->created_at)->diffForHumans(),
                'updated_at' => Carbon::parse($file->updated_at)->diffForHumans()
            ])
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
            'path' => "{$chunksPath}/{$identifier}/$fileName",
            'file_name' => $fileName,
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => $uploadedChunks
        ]);

        $upload->chunks()->updateOrCreate(['index' => $chunkIndex], [
            'path' => "{$chunksPath}/{$identifier}/{$fileName}.{$chunkIndex}",
            'size' => $currentChunk->getSize()
        ]);

        $progress = $this->calculateProgress($uploadedChunks, $totalChunks);

        if ($uploadedChunks < $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $progress,
                'identifier' => $identifier
            ], Response::HTTP_OK);
        }

        try {
            $this->assembleChunks($identifier, $fileName, $totalChunks);
            $file = $this->createFileRecord($user, $fileName);
            $this->deleteChunksAndUpload($upload);
            return response([
                'status' => 'completed',
                'progress' => 100,
                'identifier' => $identifier,
                'uploadedFile' => $file
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
        $user = Auth::user();
        $upload = $user->uploads()->where('identifier', $identifier)->firstOrFail();

        if ($upload->chunks()->count() < $totalChunks) {
            throw new Exception("Missing chunks for upload $identifier");
        }

        $uploadsDir = $user->getStoragePrefix() . '/' . $this->uploadsDir;

        if (!Storage::directoryExists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        $resource = fopen(storage_path("app/{$uploadsDir}/$fileName"), 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = fopen(storage_path("app/{$upload->path}.$i"), 'rb');
            stream_copy_to_stream($chunk, $resource);
            fclose($chunk);
        }

        fclose($resource);
    }

    private function createFileRecord(User $user, string $fileName): File
    {
        $path = $user->getStoragePrefix() . '/' . $this->uploadsDir . '/' . $fileName;

        return $user->files()->firstOrCreate(['path' => $path], [
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
            } else {
                $upload->chunks()->delete();
                $upload->delete();
            }
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
        $upload = $user->uploads()->where('identifier', $identifier)->firstOrFail();
        Storage::deleteDirectory($upload->path);
        $this->deleteChunksAndUpload($upload, true);
        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function pause(Request $request, string $identifier)
    {
        $upload = $request->user()->uploads()->where('identifier', $identifier)->firstOrFail();
        $upload->update(['status' => 'paused']);
        return response(null, Response::HTTP_NO_CONTENT);
    }
}
