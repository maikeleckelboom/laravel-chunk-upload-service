<?php

namespace App\Http\Controllers;

use App\Data\UploadData;
use App\Enum\UploadStatus;
use App\Http\Resources\UploadResource;
use App\Http\Services\UploadService;
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
            $this->uploadService->isReadyToAssemble($upload) &&
            $this->uploadService->assembleChunks($upload)
        ) {
            $upload->status = UploadStatus::DONE;
            $upload->save();
        }

        if ($upload->status === UploadStatus::PENDING) {
            return response()->json(
                UploadResource::make($upload),
                Response::HTTP_OK
            );
        }

        $this->uploadService->createFile($upload);
        $this->uploadService->cleanupAndDelete($upload);

        return response()->json(
            UploadResource::make($upload),
            Response::HTTP_CREATED
        );
    }

    public function delete(Request $request, string $identifier)
    {
        $upload = $this->uploadService->find($request->user(), $identifier);
        $this->uploadService->cleanupAndDelete($upload);
        return response()->json(null, Response::HTTP_OK);
    }

    public function pause(Request $request, string $identifier)
    {
        $upload = $this->uploadService->find($request->user(), $identifier);
        if ($upload && $upload->status === UploadStatus::PENDING) {
            $this->uploadService->pause($upload);
        }
        return response()->json(null, Response::HTTP_OK);
    }
}
