<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
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

        if ($chunkNumber !== $totalChunks) {
            return response([
                'status' => 'pending',
                'progress' => $chunkNumber / $totalChunks * 100
            ], Response::HTTP_OK);
        }

        try {
            $this->assembleChunks($identifier, $filename, $totalChunks);
            $upload->delete();

            return response([
                'status' => 'completed',
                'message' => "Uploaded {$filename}"
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response([
                'message' => 'An error occurred while assembling chunks.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function assembleChunks(string $identifier, string $filename, int $totalChunks)
    {
        $storagePrefix = Auth::user()->getStoragePrefix();
        $sourcePath = "$storagePrefix/{$this->chunksDir}/$identifier/{$filename}";
        $destination = "{$storagePrefix}/{$this->uploadsDir}/$filename";

        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunk = Storage::get("$sourcePath.$i");
            Storage::append($destination, $chunk);
        }
    }

    //    private function assembleChunksUsingNativePhp(string $identifier, string $filename, int $totalChunks)
//    {
//        $storagePrefix = Auth::user()->getStoragePrefix();
//        $sourcePath = "$storagePrefix/{$this->chunksDir}/$identifier";
//        $destination = "{$storagePrefix}/{$this->uploadsDir}/$filename";
//
//        if(!Storage::directoryExists($storagePrefix . '/' . $this->uploadsDir)){
//            Storage::makeDirectory($storagePrefix . '/' . $this->uploadsDir);
//        }
//
//        $destinationFile = fopen(storage_path("app/$destination"), 'wb');
//
//        for ($i = 1; $i <= $totalChunks; $i++) {
//            $chunk = fopen(storage_path("app/$sourcePath.$i"), 'rb');
//            stream_copy_to_stream($chunk, $destinationFile);
//            fclose($chunk);
//        }
//
//        fclose($destinationFile);
//    }

}
