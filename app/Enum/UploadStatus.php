<?php

namespace App\Enum;

enum UploadStatus: string
{
    case PENDING = 'pending';
    case DONE = 'done';
    case FAILED = 'failed';
    case QUEUED = 'queued';

    public static function toArray(): array
    {
        return array_column(UploadStatus::cases(), 'value');
    }
}
