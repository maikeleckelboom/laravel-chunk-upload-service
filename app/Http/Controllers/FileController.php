<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Models\Chunk;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function upload(Request $request)
    {

        \Validator::make($request->all(), [
            'identifier' => 'required',
            'chunk_index' => 'required|integer',
            'chunk_data' => 'required',
        ]);

        $user = $request->user();


    }

    private string $chunksDir = 'chunks';

    public function uploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'currentChunk' => 'required',
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
        $currentChunk = $request->input('currentChunk');
        $totalChunks = $request->input('totalChunks');
        $chunkIndex = $request->input('chunkIndex');
        $chunkFilename = $filename . '.' . $chunkIndex;
        $chunkPath = $this->chunksDir . '/' . $chunkFilename;

        Storage::disk('local')->put($chunkPath, $currentChunk);

        if ((int)$currentChunk === (int)$totalChunks) {

            logger()->info('All chunks uploaded 🚀');

            $this->assembleChunks($filename, $totalChunks)
                ->then(function () {
                    return response('File uploaded successfully', Response::HTTP_OK);
                })
                ->catch(function ($err) {
                    return response('Error assembling chunks: ' . $err, Response::HTTP_INTERNAL_SERVER_ERROR);
                });
        } else {
            return response('Chunk uploaded successfully', Response::HTTP_OK);
        }

        
    }

    private function assembleChunks($filename, $totalChunks)
    {
        $destination = fopen(storage_path('app/uploads/' . $filename), 'wb');
        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunkPath = storage_path('app/' . $this->chunksDir . '/' . $filename . '.' . $i);
            $chunkFile = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunkFile, $destination);
            fclose($chunkFile);
            Storage::delete($this->chunksDir . '/' . $filename . '.' . $i);
        }
        fclose($destination);

        return resolve($filename);
    }
}
