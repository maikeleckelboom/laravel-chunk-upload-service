<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Http\Services\FileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;


class FileController extends Controller
{
    public function index(Request $request)
    {
        $files = Auth::user()->files()->get();
        return response()->json($files->toArray());
    }

    public function delete(Request $request, int $id, FileService $fileService)
    {
        $fileService->delete($id);
        return response(null, Response::HTTP_NO_CONTENT);
    }
}
