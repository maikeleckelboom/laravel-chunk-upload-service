<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\Chunk;
use App\Models\File;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    private string $chunksDir = 'chunks';
    private string $uploadsDir = 'uploads';

    public function uploadChunk(Request $request)
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
        $filename = $request->input('filename');
        $chunkIndex = $request->input('chunkIndex');
        $currentChunk = $request->file('currentChunk');
        $totalChunks = $request->input('totalChunks');

        $chunkDir = $user->getStoragePath() . '/' . $this->chunksDir;
        $chunkFilename = $filename . '.' . $chunkIndex;

        $currentChunk->storeAs($chunkDir, $chunkFilename);

        if ((int)$chunkIndex === (int)$totalChunks - 1) {

            logger()->info('All chunks uploaded 🚀');

            $this->assembleChunks($filename, $totalChunks);

        } else {


            return response('Chunk uploaded successfully', Response::HTTP_OK);
        }

        return response('Chunk uploaded successfully', Response::HTTP_OK);

    }

    private function assembleChunks($filename, $totalChunks)
    {
        $storageDir = Auth::user()->getStoragePath();
        $uploadsDir = $storageDir . '/' . $this->uploadsDir;

        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        $destination = fopen(storage_path('app/' . $uploadsDir . '/' . $filename), 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $storageDir . '/' . $this->chunksDir . '/' . $filename . '.' . $i;
            $chunkStoragePath = storage_path('app/' . $chunkPath);

            $chunkFile = fopen($chunkStoragePath, 'rb');
            stream_copy_to_stream($chunkFile, $destination);
            fclose($chunkFile);

            Storage::delete($chunkPath);
        }

        fclose($destination);
    }

}
