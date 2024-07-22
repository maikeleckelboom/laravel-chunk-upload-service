<?php

namespace App\Http\Controllers;

use App\Data\UploadData;
use App\Http\Requests\ChunkUploadRequest;
use App\Http\Services\FileService;
use App\Http\Services\UploadService;

class FileUploadController extends Controller
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function upload(ChunkUploadRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();

        $response = $this->uploadService->upload($user, new UploadData(
            $validated['fileName'],
            $validated['identifier'],
            $validated['chunkIndex'],
            $validated['totalChunks'],
            $validated['currentChunk']
        ));

        $this->uploadService->assembleChunks(
            $user,
            $validated['fileName'],
            $validated['identifier']
        );

        $filePath = "{$user->getStoragePrefix()}/{$this->uploadsDir}/{$validated['fileName']}";
        $file = (new FileService())->create($user, $filePath);

//        $upload->update(['status' => 'completed']);
//
//        $this->softDelete($upload);

        return response($response, $response['code']);
    }

}
