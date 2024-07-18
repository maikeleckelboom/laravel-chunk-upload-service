<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\Chunk;
use App\Models\File;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    private string $chunksDirName = 'chunks';
    private string $uploadsDirName = 'uploads';

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'currentChunk' => 'required|file',
            'totalChunks' => 'required|integer',
            'chunkIndex' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error-validation',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $filename = $request->input('filename');
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');

        $chunkDir = $request->user()->getStoragePath() . '/' . $this->chunksDirName;
        $chunkFilename = $filename . '.' . $chunkIndex;

        $currentChunk->storeAs($chunkDir, $chunkFilename);

        if ($chunkIndex === $totalChunks - 1) {
            $this->assembleChunks($filename, $totalChunks);
            return response([
                'status' => 'completed',
                'progress' => 100,
                'message' => 'uploaded ' . $totalChunks . ' chunks.',
            ], Response::HTTP_OK);
        }

        return response([
            'status' => 'pending',
            'progress' => ($chunkIndex + 1) / $totalChunks * 100,
            'message' => 'uploaded ' . ($chunkIndex + 1) . ' of ' . $totalChunks . ' chunks.',
        ], Response::HTTP_OK);
    }

    private function assembleChunks(string $filename, int $totalChunks)
    {
        $storagePath = Auth::user()->getStoragePath();
        $uploadsDir = $storagePath . '/' . $this->uploadsDirName;

        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        $destination = fopen(storage_path('app/' . $uploadsDir . '/' . $filename), 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $storagePath . '/' . $this->chunksDirName . '/' . $filename . '.' . $i;
            $chunkStoragePath = storage_path('app/' . $chunkPath);
            $chunkFile = fopen($chunkStoragePath, 'rb');
            stream_copy_to_stream($chunkFile, $destination);
            fclose($chunkFile);
            Storage::delete($chunkPath);
        }

        fclose($destination);
    }

}
