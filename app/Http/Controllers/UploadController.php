<?php

namespace App\Http\Controllers;

use App\Data\UploadData;
use App\Http\Requests\ChunkUploadRequest;
use App\Http\Services\FileService;
use App\Http\Services\UploadService;
use App\Models\Upload;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{

    private string $chunksDir = 'temp/chunks';
    private string $uploadsDir = 'uploads';

    public function index(Request $request)
    {
        $uploads = $request->user()->uploads()->withoutTrashed()->get();
        return response()->json($uploads->toArray());
    }

    public function upload(Request $request)
    {
        $data = UploadData::from($request->all());

        $user = $request->user();

        $response = (new UploadService())->upload($user, $data);

        $fileName = $request->input('fileName');
        $identifier = $request->input('identifier');
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $user = $request->user();
        $chunksPath = $user->getStoragePrefix() . '/' . $this->chunksDir;

        // .....


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

        $progress = ($uploadedChunks / $totalChunks) * 100;

        if ($uploadedChunks < $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $progress,
                'identifier' => $identifier
            ], Response::HTTP_OK);
        }

        try {
            $this->assembleChunks($identifier, $fileName, $totalChunks);

            $filePath = "{$user->getStoragePrefix()}/{$this->uploadsDir}/{$fileName}";
            $file = (new FileService())->create($user, $filePath);

            $upload->update(['status' => 'completed']);

            $this->softDelete($upload);

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

    private function alreadyUploaded(string $identifier, int $chunkIndex, int $totalChunks, $user): bool
    {
        $upload = $user->uploads()->where('identifier', $identifier)->first();

        if (!$upload) {
            return false;
        }

        if ($upload->uploaded_chunks >= $totalChunks) {
            return true;
        }

        return $upload->chunks()->where('index', $chunkIndex)->exists();
    }

    /**
     * @throws Exception
     */
    public function assembleChunks(string $identifier, string $fileName, int $totalChunks)
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

    public function softDelete(Upload $upload): void
    {
        DB::transaction(function () use ($upload) {
            $upload->chunks()->delete();
            $upload->delete();
        });
    }

    public function forceDelete(Upload $upload): void
    {
        $upload->chunks->each(fn($chunk) => Storage::delete($chunk->path));
        Storage::deleteDirectory($upload->path);

        DB::transaction(function () use ($upload) {
            $upload->chunks()->forceDelete();
            $upload->forceDelete();
        });
    }

    public function pause(Request $request, string $identifier)
    {
        $upload = $request->user()->uploads()->where('identifier', $identifier)->firstOrFail();
        $upload->update(['status' => 'paused']);
        return response(null, Response::HTTP_NO_CONTENT);
    }
}
