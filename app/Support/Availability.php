<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 1日の限ごとに「空いている講師」と「席が片方空いている講師」を求める
 */
class Availability
{
    /**
     * @param  Collection<int, User>  $teachers  在籍中の講師
     * @param  Collection<int, TimeSlot>  $slots  その日の時間割の授業枠
     * @param  Collection<int, Shift>  $shifts  その日のシフト（timeSlot を読み込み済み）
     * @return list<array{period: int, label: string, time: string, free: Collection<int, User>, half: Collection<int, User>}>
     */
    public static function forDay(Collection $teachers, Collection $slots, Collection $shifts): array
    {
        $byTeacher = $shifts->groupBy('user_id');
        $result = [];

        foreach ($slots->groupBy('period') as $period => $periodSlots) {
            $start = (string) $periodSlots->min('start_time');
            $end = (string) $periodSlots->max('end_time');
            $free = collect();
            $half = collect();

            foreach ($teachers as $teacher) {
                // この限の時間に重なる授業
                $mine = $byTeacher->get($teacher->id, collect())
                    ->filter(fn (Shift $shift) => $shift->timeSlot->start_time < $end && $start < $shift->timeSlot->end_time);

                if ($mine->isEmpty()) {
                    $free->push($teacher);

                    continue;
                }

                // 見ている生徒が最も多い時刻（いずれかの授業の開始時刻）で2人に満たなければ、席が片方空いている
                $instants = $periodSlots->pluck('start_time')
                    ->merge($mine->map(fn (Shift $shift) => $shift->timeSlot->start_time))
                    ->unique();
                $peak = $instants
                    ->map(fn (string $time) => $mine->filter(fn (Shift $shift) => $shift->timeSlot->isInSessionAt($time))
                        ->sum(fn (Shift $shift) => $shift->occupiedSeats()))
                    ->max();

                if ($peak < Shift::MAX_SEATS) {
                    $half->push($teacher);
                }
            }

            $result[] = [
                'period' => (int) $period,
                'label' => "{$period}限",
                'time' => substr($start, 0, 5).'〜'.substr($end, 0, 5),
                'free' => $free,
                'half' => $half,
            ];
        }

        return $result;
    }
}
