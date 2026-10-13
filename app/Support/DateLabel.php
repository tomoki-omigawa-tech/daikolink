<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DateLabel
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 例：10/13（火）
     */
    public static function date(CarbonInterface $date): string
    {
        return $date->format('n/j').'（'.self::WEEKDAYS[$date->dayOfWeek].'）';
    }

    /**
     * 例：10/13（火） 11:20
     */
    public static function dateTime(CarbonInterface $dateTime): string
    {
        return self::date($dateTime).' '.$dateTime->format('H:i');
    }

    /**
     * 0（日）〜6（土）
     */
    public static function weekday(int $dayOfWeek): string
    {
        return self::WEEKDAYS[$dayOfWeek % 7];
    }
}
