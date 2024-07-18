<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function getChunkDataAttribute($value): string
    {
        return base64_decode($value);
    }

    public function setChunkDataAttribute($value): void
    {
        $this->attributes['chunk_data'] = base64_encode($value);
    }
}
