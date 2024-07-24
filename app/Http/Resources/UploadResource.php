<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'fileName' => $this->file_name,
            'status' => $this->status,
            'progress' => $this->progress,
            'totalChunks' => $this->total_chunks,
            'uploadedChunks' => $this->uploaded_chunks,
            'file' => $this->when($this->status === 'done', FileResource::make($this->file))
        ];
    }
}
