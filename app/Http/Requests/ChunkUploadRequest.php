<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChunkUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'filename' => 'required|string',
            'identifier' => 'required|string',
            'chunkNumber' => 'required|integer|min:1',
            'totalChunks' => 'required|integer|min:1',
            'currentChunk' => 'required|file',
//            'chunkSize' => 'sometimes|integer',
//            'totalSize' => 'sometimes|integer',
        ];
    }
}
