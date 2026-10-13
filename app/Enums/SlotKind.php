<?php

namespace App\Enums;

enum SlotKind: string
{
    case Secondary = 'secondary';
    case ElementaryFirst = 'elementary_first';
    case ElementarySecond = 'elementary_second';
    case ElementaryGroup = 'elementary_group';

    public function label(): string
    {
        return match ($this) {
            self::Secondary => '中高生',
            self::ElementaryFirst => '小学生1/2',
            self::ElementarySecond => '小学生2/2',
            self::ElementaryGroup => '小学生集団',
        };
    }

    public function isGroup(): bool
    {
        return $this === self::ElementaryGroup;
    }

    public function isElementary(): bool
    {
        return $this !== self::Secondary;
    }

    /**
     * 同じ限の中での表示順（出勤簿と同じ：集団 → 中高生 → 1/2 → 2/2）
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::ElementaryGroup => 1,
            self::Secondary => 2,
            self::ElementaryFirst => 3,
            self::ElementarySecond => 4,
        };
    }
}
