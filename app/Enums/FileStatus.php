<?php

namespace App\Enums;

enum FileStatus: int
{
    case Pending = 0;
    case Processing = 1;
    case Failed = 2;
    case Completed = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Failed => 'Failed',
            self::Completed => 'Completed',
        };
    }
}
