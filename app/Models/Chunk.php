<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chunk extends Model
{
    use SoftDeletes;

    protected $guarded = false;

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
