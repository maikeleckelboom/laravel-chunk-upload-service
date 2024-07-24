<?php

namespace App\Models\Media;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    protected $guarded = false;

    protected $appends = [
        'url'
    ];

    protected $casts = [
        'size' => 'integer'
    ];

    protected $hidden = [
        'path'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return config('app.url') . Storage::url($this->path);
    }
}
