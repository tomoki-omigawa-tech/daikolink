<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum TimetablePattern: string
{
    case Weekday = 'weekday';
    case Saturday = 'saturday';
    case Intensive = 'intensive';

    public function label(): string
    {
        return match ($this) {
            self::Weekday => '平日',
            self::Saturday => '土曜',
            self::Intensive => '講習',
        };
    }

    /**
     * 曜日から決まる既定のパターン。月〜金は平日（祝日も曜日どおり）、土曜は土曜、日曜は休み（講習のときだけ手動で講習にする）
     */
    public static function defaultFor(CarbonInterface $date): ?self
    {
        if ($date->isSunday()) {
            return null;
        }

        return $date->isSaturday() ? self::Saturday : self::Weekday;
    }
}
