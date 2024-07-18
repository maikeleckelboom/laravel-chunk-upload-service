<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\Chunk;
use App\Models\File;
use App\Models\Upload;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Str;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    private string $chunksDirName = 'chunks';
    private string $uploadsDirName = 'uploads';

    public function upload(ChunkUploadRequest $request)
    {
        $user = $request->user();
        $identifier = $request->input('identifier');
        $filename = $request->input('filename');
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $chunkPath = $user->getStoragePrefix() . '/' . $this->chunksDirName;

        $currentChunk->storeAs($chunkPath, "{$identifier}.{$chunkIndex}");

        $chunkNumber = ++$chunkIndex;

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
                'progress' => $chunkNumber / $totalChunks * 100,
                'message' => 'uploaded ' . $chunkNumber . ' of ' . $totalChunks . ' chunks.',
            ], Response::HTTP_OK);
        }

        try {
            $this->assembleChunks($identifier, $filename, $totalChunks);

            $upload->delete();

            return response([
                'status' => 'completed',
                'message' => 'uploaded ' . $totalChunks . ' chunks.',
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
        $sourcePath = "$storagePrefix/{$this->chunksDirName}/$identifier";
        $destination = "{$storagePrefix}/{$this->uploadsDirName}/$filename";

        for ($i = 0; $i < $totalChunks; $i++) {
            Storage::move("$sourcePath.$i", $destination);
        }
    }

    public function pause(Request $request, string $identifier)
    {

    }

    public function uploadStatus(Request $request, $filename)
    {
        $upload = Upload::where('user_id', $request->user()->id)->where('filename', $filename)->first();

        if (!$upload->exists()) {
            return response([
                'status' => 'not-found',
                'message' => 'No upload record found for this file.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response([
            'status' => $upload->status,
            'progress' => $upload->uploaded_chunks / $upload->total_chunks * 100,
            'message' => 'uploaded ' . $upload->uploaded_chunks . ' of ' . $upload->total_chunks . ' chunks.',
        ], Response::HTTP_OK);
    }


}
