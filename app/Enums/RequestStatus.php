<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Open = 'open';
    case Applied = 'applied';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Open => '募集中',
            self::Applied => '応募あり',
            self::Confirmed => '確定',
            self::Cancelled => '取消',
            self::Expired => '不成立',
        };
    }

    /**
     * まだ代講者が決まっていない状態
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Applied], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Open->value, self::Applied->value];
    }
}
