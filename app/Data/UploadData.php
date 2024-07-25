<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UploadData extends Data
{
    public function __construct(
        public string       $fileName,
        public int          $fileSize,
        public string       $fileType,
        public string       $identifier,
        public int          $chunkIndex,
        public int          $totalChunks,
        public UploadedFile $currentChunk,
    )
    {
    }
}
