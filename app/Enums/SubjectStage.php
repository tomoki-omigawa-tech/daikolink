<?php

namespace App\Enums;

enum SubjectStage: string
{
    case Elementary = 'elementary';
    case Junior = 'junior';
    case Senior = 'senior';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Elementary => '小学',
            self::Junior => '中学',
            self::Senior => '高校',
            self::Other => 'その他',
        };
    }
}
