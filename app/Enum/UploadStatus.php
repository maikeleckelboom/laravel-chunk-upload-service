<?php

namespace App\Enum;

enum UploadStatus: string
{
    case PENDING = 'pending';
    case DONE = 'done';
    case PAUSED = 'paused';
    case FAILED = 'failed';

    public static function toArray(): array
    {
        return array_column(UploadStatus::cases(), 'value');
    }
}
