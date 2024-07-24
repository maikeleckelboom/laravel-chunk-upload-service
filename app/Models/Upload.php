<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upload extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer',
    ];

    protected $appends = [
        'progress'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    public function getProgressAttribute(): float
    {
        return ($this->uploaded_chunks / $this->total_chunks) * 100;
    }
}
