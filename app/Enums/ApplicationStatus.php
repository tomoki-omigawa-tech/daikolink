<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '応募中',
            self::Accepted => '採用',
            self::Rejected => '見送り',
            self::Withdrawn => '取り消し',
        };
    }
}
