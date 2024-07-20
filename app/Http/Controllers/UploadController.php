<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function index(Request $request)
    {
        $uploads = Auth::user()->uploads()->withoutTrashed()->get();

        $collection = collect($uploads)
            ->sortByDesc('created_at')
            ->map(fn($file) => [
                ...collect($file)->except('created_at', 'updated_at')->toArray(),
                'created_at' => Carbon::parse($file->created_at)->diffForHumans(),
                'updated_at' => Carbon::parse($file->updated_at)->diffForHumans()
            ])
            ->values();

        return response()->json($collection->toArray());
    }

    public function delete(Request $request, $id)
    {
        $upload = Auth::user()->uploads()->findOrFail($id);

        DB::transaction(function () use ($upload) {
            $upload->chunks()->delete();
            $upload->forceDelete();
        });

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
