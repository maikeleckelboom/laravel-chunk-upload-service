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

        $user = $request->user();
        $filename = Str::lower($request->input('filename'));
        $chunkIndex = (int)$request->input('chunkIndex');
        $totalChunks = (int)$request->input('totalChunks');
        $currentChunk = $request->file('currentChunk');


        $chunkDir = $user->getStoragePath() . '/' . $this->chunksDirName;
        $chunkFilename = $filename . '.' . $chunkIndex;

        $currentChunk->storeAs($chunkDir, $chunkFilename);

        $upload = $user->uploads()->updateOrCreate(
            [
                'filename' => $filename,
                'status' => 'pending',
            ],
            [
                'total_chunks' => $totalChunks,
                'uploaded_chunks' => $chunkNumber = ++$chunkIndex,
            ]
        );

        if ($chunkNumber === $totalChunks) {

            try {
                $this->assembleChunks($filename, $totalChunks);
                $upload->delete();
            } catch (Exception $e) {
                return response([
                    'message' => 'An error occurred while assembling chunks.',
                    'error' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response([
                'status' => 'completed',
                'message' => 'uploaded ' . $totalChunks . ' chunks.',
            ], Response::HTTP_OK);
        }

        return response([
            'status' => 'pending',
            'progress' => $chunkNumber / $totalChunks * 100,
            'message' => 'uploaded ' . $chunkNumber . ' of ' . $totalChunks . ' chunks.',
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
        }

        fclose($destination);


        Storage::delete($storagePath . '/' . $this->chunksDirName . '/' . $filename . '.*');

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
