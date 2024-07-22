<?php

namespace App\Data;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UploadData extends Data
{
    public function __construct(
        public string       $fileName,
        public string       $identifier,
        public int          $chunkIndex,
        public int          $totalChunks,
        public int          $totalSize,
        public UploadedFile $currentChunk,
    )
    {
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'fileName' => 'required|string',
            'identifier' => 'required|string',
            'chunkIndex' => 'required|integer|min:0',
            'totalChunks' => 'required|integer|min:1',
            'totalSize' => 'required|integer',
            'currentChunk' => 'required|file',
//            'chunkSize' => 'integer',
        ];
    }
}
