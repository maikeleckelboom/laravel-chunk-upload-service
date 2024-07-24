<?php

namespace App\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    protected $guarded = false;

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
