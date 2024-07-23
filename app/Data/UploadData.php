<?php

namespace App\Data;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UploadData extends Data
{
    public function __construct(
        public string       $fileName,
        public int          $fileSize,
        public string       $identifier,
        public int          $chunkIndex,
        public int          $totalChunks,
        public UploadedFile $currentChunk,
    )
    {
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public static function rules(): array
    {
        return [
            'fileName' => 'required|string',
            'fileSize' => 'required|integer',
            'identifier' => 'required|string',
            'chunkIndex' => 'required|integer|min:0',
            'totalChunks' => 'required|integer|min:1',
            'currentChunk' => 'required|file',
        ];
    }
}
