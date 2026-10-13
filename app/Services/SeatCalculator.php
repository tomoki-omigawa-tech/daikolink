<?php

namespace App\Services;

use App\Models\TimeSlot;
use Illuminate\Support\Collection;

class SeatCalculator
{
    /**
     * 同じ時刻に見る生徒数の最大値（最大になるのは、いずれかの授業の開始時刻）
     *
     * @param  Collection<int, array{slot: TimeSlot, seats: int}>  $entries
     */
    public static function peak(Collection $entries): int
    {
        $peak = 0;

        foreach ($entries as $entry) {
            $load = $entries
                ->filter(fn (array $other) => $other['slot']->isInSessionAt($entry['slot']->start_time))
                ->sum('seats');
            $peak = max($peak, (int) $load);
        }

        return $peak;
    }
}
