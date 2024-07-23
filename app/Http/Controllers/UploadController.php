<?php

namespace App\Http\Controllers;

use App\Data\UploadData;
use App\Http\Services\UploadService;
use App\UploadStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function index(Request $request)
    {
        return response()->json($this->uploadService->getUploadQueue($request->user()));
    }

    public function upload(Request $request)
    {
        $upload = $this->uploadService->uploadChunk(
            $request->user(),
            UploadData::validateAndCreate($request->all())
        );

        if (
            $this->uploadService->hasUploadedAllChunks($upload) &&
            $this->uploadService->assembleChunks($upload)
        ) {
            $upload->status = UploadStatus::DONE;
            $upload->save();
        }

        if ($upload->status === UploadStatus::PENDING) {
            return response()->json([
                'status' => $upload->status,
                'progress' => ($upload->uploaded_chunks / $upload->total_chunks) * 100
            ]);
        }

        $file = $this->uploadService->createFileForUpload($upload);

        if($file->exists()){
            $this->uploadService->delete($upload);
        }

        return response()->json([
            'status' => $upload->status,
            'progress' => 100,
            'file' => $file->toArray()
        ]);
    }

    public function delete(Request $request, string $identifier)
    {
        $upload = $this->uploadService->find($request->user(), $identifier);
        return response()->json($this->uploadService->delete($upload), Response::HTTP_OK);
    }

    public function pause(Request $request, string $identifier)
    {
        $upload = $this->uploadService->find($request->user(), $identifier);
        return response()->json($this->uploadService->pause($upload), Response::HTTP_OK);
    }
}
