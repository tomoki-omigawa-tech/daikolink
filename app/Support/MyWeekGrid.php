<?php

namespace App\Support;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * 1人の講師の1週間（月〜土）のシフトを「縦＝限、横＝曜日（2列）」の表にする
 */
class MyWeekGrid
{
    /**
     * @return array{days: list<array<string, mixed>>, rows: list<array{period: int, cells: list<list<array<string, mixed>>>}>, lastUpdated: Shift|null}
     */
    public static function build(User $user, CarbonInterface $monday): array
    {
        $range = [$monday->toDateString(), $monday->copy()->addDays(5)->toDateString()];

        $shifts = Shift::with(['timeSlot', 'subject', 'activeRequests', 'updatedBy'])
            ->where('user_id', $user->id)
            ->whereBetween('date', $range)
            ->get();
        $byDate = $shifts->groupBy(fn (Shift $shift) => $shift->date->toDateString());
        $closed = ClosedDay::whereBetween('date', $range)->get()
            ->keyBy(fn (ClosedDay $day) => $day->date->toDateString());
        $slotsByPattern = TimeSlot::orderBy('sort_order')->get()
            ->groupBy(fn (TimeSlot $slot) => $slot->pattern->value);

        $days = [];
        foreach (range(0, 5) as $offset) {
            $date = $monday->copy()->addDays($offset);
            $key = $date->toDateString();
            $dayShifts = $byDate->get($key, collect());
            $pattern = $dayShifts->first()?->timeSlot->pattern ?? TimetablePattern::defaultFor($date);

            $days[] = [
                'date' => $date,
                'pattern' => $pattern,
                'closed' => $closed->get($key),
                'shifts' => $dayShifts,
                'slots' => $pattern !== null ? $slotsByPattern->get($pattern->value, collect()) : collect(),
            ];
        }

        $periods = collect($days)
            ->flatMap(fn (array $day) => $day['slots']->pluck('period'))
            ->unique()
            ->sort()
            ->values();

        $rows = [];
        foreach ($periods as $period) {
            $period = (int) $period;
            $hasElementary = collect($days)->contains(
                fn (array $day) => $day['slots']->contains(fn (TimeSlot $slot) => $slot->period === $period && $slot->kind === SlotKind::ElementaryFirst)
            );
            $rowCount = $hasElementary ? 2 : 1;
            $cells = array_fill(0, $rowCount, []);

            foreach ($days as $day) {
                $periodSlots = $day['slots']->filter(fn (TimeSlot $slot) => $slot->period === $period);
                if ($day['closed'] !== null || $periodSlots->isEmpty()) {
                    $cells[0][] = self::cell(null, 'none', $rowCount, 2);

                    continue;
                }

                $mine = $day['shifts']->filter(fn (Shift $shift) => $shift->timeSlot->period === $period);
                $group = $mine->first(fn (Shift $shift) => $shift->timeSlot->kind->isGroup());
                if ($group !== null) {
                    $cells[0][] = self::cell($group, 'group', $rowCount, 2);

                    continue;
                }

                $dayHasElementary = $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind === SlotKind::ElementaryFirst);

                foreach ([1, 2] as $seat) {
                    $find = fn (SlotKind $kind) => $mine->first(fn (Shift $shift) => $shift->seat === $seat && $shift->timeSlot->kind === $kind);

                    $secondary = $find(SlotKind::Secondary);
                    if ($secondary !== null || ! $dayHasElementary || $rowCount === 1) {
                        $cells[0][] = self::cell($secondary, $secondary ? 'secondary' : 'empty', $rowCount, 1);

                        continue;
                    }

                    $first = $find(SlotKind::ElementaryFirst);
                    $second = $find(SlotKind::ElementarySecond);
                    if ($first !== null || $second !== null) {
                        $cells[0][] = self::cell($first, 'first', 1, 1);
                        $cells[1][] = self::cell($second, 'second', 1, 1);
                    } else {
                        $cells[0][] = self::cell(null, 'empty', 2, 1);
                    }
                }
            }

            $rows[] = ['period' => $period, 'cells' => $cells];
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'lastUpdated' => $shifts->sortByDesc('updated_at')->first(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cell(?Shift $shift, string $type, int $rowspan, int $colspan): array
    {
        return [
            'type' => $type,
            'rowspan' => $rowspan,
            'colspan' => $colspan,
            'label' => $shift?->lessonLabel() ?? '',
            'time' => $shift?->timeSlot->timeRange() ?? '',
            'manual' => $shift !== null && $shift->is_manual,
            'requested' => $shift !== null && $shift->activeRequests->isNotEmpty(),
        ];
    }
}
