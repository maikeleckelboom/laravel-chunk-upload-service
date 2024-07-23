<?php

namespace App;

enum UploadStatus: string
{
    case PENDING = 'pending';
    case DONE = 'completed';
    case PAUSED = 'paused';
    case FAILED = 'failed';

    public static function toArray(): array
    {
        return array_column(UploadStatus::cases(), 'value');
    }
}
